<?php

declare(strict_types=1);

namespace Drupal\joinup\Context;

use Behat\Gherkin\Node\TableNode;
use Behat\Mink\Exception\ExpectationException;
use Behat\Step\Given;
use Behat\Step\Then;
use Behat\Step\When;
use Drupal\Component\Render\MarkupInterface;
use Drupal\DrupalExtension\Context\RawDrupalContext;
use Drupal\DrupalExtension\TagTrait;
use Drupal\joinup\Traits\MailCollectorTrait;
use Drupal\joinup\Traits\UserTrait;
use Drupal\joinup\Traits\UtilityTrait;
use Drupal\joinup_notification\MailContentTypeHeader;
use Drupal\message\Entity\MessageTemplate;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Behat step definitions for testing notifications.
 */
class JoinupNotificationContext extends RawDrupalContext {

  use MailCollectorTrait;
  use TagTrait;
  use UserTrait;
  use UtilityTrait;

  /**
   * Asserts that an email has been sent.
   *
   * Table format:
   * | template           | Comment deletion |
   * | recipient          | username0123     |
   * | recipient_mail     | usertest@ex.com  |
   * | subject            | The mail subject |
   * | body               | The message body |
   * | html               | yes/no           |
   * | signature_required | yes/no           |
   *
   * - The 'template' can be either the name or the description of the message
   *   template. If the template row is not present, no matching on template
   *   will be done. This is needed for system mails (like the ones sent by the
   *   user module).
   * - The recipient and recipient_mail are not both mandatory. If a
   *   recipient_mail is not used, the user will be loaded and their email will
   *   be used.
   * - The 'body' is a partial text based match.
   * - The 'html' and 'signature_required' columns can be either 'yes' or 'no'.
   *   When not present, 'yes' is assumed.
   *
   * @param \Behat\Gherkin\Node\TableNode $table
   *   The mail data table.
   *
   * @throws \Exception
   *   Throws an exception when a parameter is not the expected one.
   */
  #[Then('the following email should have been sent:')]
  public function assertEmailSent(TableNode $table): void {
    /** @var string $template */
    /** @var string $recipient */
    /** @var string $recipient_mail */
    /** @var string $bcc */
    /** @var string $from */
    /** @var string $body */
    /** @var string $subject */
    /** @var string $signature_required */
    /** @var string $recipient_list_contains */
    extract($table->getRowsHash());

    if (empty($recipient) && !empty($recipient_list_contains)) {
      $recipient = $recipient_list_contains;
    }

    // Translate the human readable value for the requirement of the signature
    // to a boolean. This defaults to TRUE.
    $signature_required = !isset($signature_required) || $signature_required !== 'no';

    // If the 'html' row is not present, assume a 'yes'. Convert 'yes' to TRUE
    // and 'no' to FALSE.
    $html = empty($html) || $html === 'yes';

    if (!empty($recipient_mail)) {
      if (!filter_var($recipient_mail, FILTER_VALIDATE_EMAIL)) {
        throw new \Exception("Recipient {$recipient_mail} is not a valid e-mail address.");
      }
      $recipient = $recipient_mail;
    }
    else {
      $user = user_load_by_name($recipient);
      if (empty($user)) {
        throw new \Exception("User $recipient was not found.");
      }
      if (empty($user->getEmail())) {
        throw new \Exception("User $recipient does not have an e-mail address.");
      }
      $recipient = $user->getEmail();
    }

    $mails = $this->getEmailsBySubjectAndMail($subject, $recipient, FALSE, !empty($recipient_list_contains));
    $email_found = FALSE;
    foreach ($mails as $mail) {
      // Check the sender email if it is set.
      if (!empty($from) && $mail['from'] !== $from) {
        continue;
      }

      // \Drupal\Core\Mail\Plugin\Mail\PhpMail::format() automatically wraps the
      // mail body line to a certain amount of characters (77 by default).
      // Spaces are also removed.
      // @see \Drupal\Core\Mail\Plugin\Mail\PhpMail::format()
      $mail_body = trim((string) $mail['body']);
      $mail_body = str_replace("\r\n", "\n", $mail_body);
      $mail_body = preg_replace("/[\n\t]+/", ' ', $mail_body);

      // If the original mail body consists of renderable markup, we need to:
      // - undo the encoding done by Twig to quotes;
      // - remove the HTML to simplify the matching on the body text.
      // @see vendor/twig/twig/lib/Twig/Extension/Core.php:1034
      if ($mail['body'] instanceof MarkupInterface) {
        $mail_body = strip_tags($mail_body);
        $mail_body = htmlspecialchars_decode($mail_body, ENT_QUOTES | ENT_SUBSTITUTE);
      }

      // Ensure that there are no un-replaced arguments in the mail subject and
      // body.
      if (preg_match('/\B@\S/', $mail_body) || preg_match('/\B@\S/', $mail['subject'])) {
        throw new \Exception('There were arguments found in the subject or the body of the email that were not replaced.');
      }

      // Check the existence or absence of the signature.
      $signature_present = preg_match('/Kind regards,\s{0,2}The Portal Support Team/', $mail_body);
      if ($signature_required && !$signature_present) {
        throw new \Exception('The signature of the email was not found or is not correct.');
      }
      elseif (!$signature_required && $signature_present) {
        throw new \Exception('The signature of the email was found in the email but should not be present.');
      }

      if (isset($body)) {
        // Since the body field has url tokens, we cannot provide a full body
        // text because we don't have the url available. Because of this, we
        // just match partial text.
        $body = preg_replace("/\s+/", ' ', $body);
        $mail_body = preg_replace("/\s+/", ' ', $mail_body);
        if (!str_contains($mail_body, $body)) {
          continue;
        }
      }

      // If the template is present, try to load the related message template
      // entity.
      if (isset($template)) {
        $message_template = MessageTemplate::load($mail['key']);
        if ($template !== $message_template->getDescription() && $template !== $message_template->getLabel()) {
          continue;
        }
      }

      if (isset($bcc)) {
        $this->assertCondition(str_contains($mail['headers']['Bcc'] ?? '', $bcc), 'Bcc is properly set to the email.');
      }

      // We found a match. Stop searching.
      $email_found = TRUE;
      break;
    }

    $this->assertCondition($email_found, "Did not find expected email to '$recipient' with subject '$subject'.");

    // If the previous assertion passed, the $mail variable contains the correct
    // mail.
    /** @var array $mail */
    $expected = $html ? MailContentTypeHeader::TextHtml : MailContentTypeHeader::TextPlain;
    if ($html) {
      $this->assertCondition(str_contains($mail['headers']['Content-Type'], $expected->value), 'The mail is not sent as HTML.');
    }
    else {
      $this->assertCondition(str_contains($mail['headers']['Content-Type'], $expected->value), 'The mail is not sent as plain text.');
    }
  }

  /**
   * Asserts that an email has not been sent.
   *
   * See ::assertEmailSent() for the structure of the table node argument.
   *
   * @param \Behat\Gherkin\Node\TableNode $table
   *   The mail data table.
   *
   * @throws \Exception
   *   Throws an exception when a parameter is not the expected one.
   *
   * @see self::assertEmailSent()
   */
  #[Then('the following email should not have been sent:')]
  public function assertEmailNotSent(TableNode $table): void {
    try {
      // Reusing ::assertEmailSent().
      $this->assertEmailSent($table);
    }
    catch (\Exception $e) {
      // If the assertion above throws an exception, it means that the email
      // was not sent, so we can return.
      return;
    }

    throw new \Exception('E-mail was sent.');
  }

  /**
   * Asserts that an email has been sent and contains some pieces of text.
   *
   * @param string $username_or_email
   *   The username or user email.
   * @param string $subject
   *   The subject of the email.
   * @param \Behat\Gherkin\Node\TableNode $table
   *   The mail data table.
   *
   * @throws \Exception
   *   Throws an exception when a parameter is not the expected one.
   */
  #[Then('the email sent to :username_or_email with subject :subject contains the( following lines of) text:')]
  public function assertEmailSentAndContainsText(string $username_or_email, string $subject, TableNode $table): void {
    $this->emailSentHelper(TRUE, $username_or_email, $subject, $table);
  }

  /**
   * Asserts that an email contains attachment.
   *
   * @param string $email
   *   Email address.
   * @param string $subject
   *   The subject of the email.
   * @param string $file
   *   File name.
   *
   * @throws \Exception
   *   Throws an exception when a parameter is not the expected one.
   */
  #[Given('/^the email sent to "([^"]*)" with subject "([^"]*)" contains "([^"]*)" attachment$/')]
  public function theEmailSentToWithSubjectContainsAttachment(string $email, string $subject, string $file): void {
    $mails = $this->getEmailsBySubjectAndMail($subject, $email);
    foreach ($mails as $mail) {
      foreach ($mail['params']['attachments'] ?? [] as $attachment) {
        if ($attachment['filename'] === $file) {
          return;
        }
      }
    }

    throw new \InvalidArgumentException("Attachment $file has not been found.");
  }

  /**
   * Asserts that an email contains header value.
   *
   * @param string $email
   *   Email address.
   * @param string $subject
   *   The subject of the email.
   * @param string $header_name
   *   The header name (e.g.: "Content-type").
   * @param string $header_value
   *   The header value (e.g.: "text/html").
   *
   * @throws \Exception
   *   Throws an exception when a parameter is not the expected one.
   */
  #[Given('/^the email sent to "([^"]*)" with subject "([^"]*)" contains header "([^"]*)" with value "([^"]*)"$/')]
  public function theEmailSentToWithSubjectContainsHeaderValue(string $email, string $subject, string $header_name, string $header_value): void {
    $mails = $this->getEmailsBySubjectAndMail($subject, $email);
    foreach ($mails as $mail) {
      if (isset($mail['headers'][$header_name]) && $mail['headers'][$header_name] === $header_value) {
        return;
      }
    }

    throw new \InvalidArgumentException("Header $header_name with value $header_value has not been found.");
  }

  /**
   * Asserts that no email of the relevant criteria have been sent.
   *
   * @param string $username_or_email
   *   The username or user email.
   * @param string $subject
   *   The subject of the email.
   * @param \Behat\Gherkin\Node\TableNode $table
   *   The mail data table.
   *
   * @throws \Exception
   *   Throws an exception when a parameter is not the expected one.
   */
  #[Then('the email sent to :username_or_email with subject :subject should not contain the( following lines of) text:')]
  public function assertEmailSentNotContainsText(string $username_or_email, string $subject, TableNode $table): void {
    $this->emailSentHelper(FALSE, $username_or_email, $subject, $table);
  }

  /**
   * Provides a helper for 'email sent' assertions.
   *
   * @param bool $match
   *   Whether we check existence or lack of the email.
   * @param string $username_or_email
   *   The username or user email.
   * @param string $subject
   *   The subject of the email.
   * @param \Behat\Gherkin\Node\TableNode $table
   *   The mail data table.
   *
   * @throws \Exception
   *   Throws an exception when a parameter is not the expected one.
   */
  protected function emailSentHelper(bool $match, string $username_or_email, string $subject, TableNode $table): void {
    $lines_of_text = $table->getColumn(0);
    if ($account = user_load_by_name($username_or_email)) {
      $recipient = $account->getEmail();
    }
    elseif (\Drupal::getContainer()->get('email.validator')->isValid($username_or_email)) {
      $recipient = $username_or_email;
    }
    else {
      throw new \InvalidArgumentException("'$username_or_email' is neither a valid username, nor a valid email.");
    }
    $mails = $this->getEmailsBySubjectAndMail($subject, $recipient);

    foreach ($mails as $mail) {
      $body = ($mail['body'] instanceof MarkupInterface) ? $this->formatMailBodyMarkup($mail['body']) : $this->formatMailBodyText($mail['body']);
      foreach ($lines_of_text as $text) {
        $pos = strpos($body, $text);
        if ($match && $pos === FALSE) {
          continue 2;
        }
        if (!$match && $pos !== FALSE) {
          throw new ExpectationException("Email with subject '$subject' sent to '$recipient' contains the text '$text'.", $this->getSession()->getDriver());
        }
      }
      if ($match) {
        return;
      }
    }
    if ($match) {
      throw new ExpectationException("Did not find expected email to '$recipient' with subject '$subject'.", $this->getSession()->getDriver());
    }
  }

  /**
   * Clears the cache of the mail collector.
   *
   * Use this if you don't want to match on emails that have been sent earlier
   * in the scenario.
   */
  #[Given('I mark all emails as read')]
  public function clearMailCollectorCache(): void {
    \Drupal::state()->set('system.test_mail_collector', []);
    \Drupal::state()->resetCache();
  }

  /**
   * Asserts that a certain amount of e-mails have been sent.
   *
   * @param int $count
   *   The expected count of e-mails sent.
   * @param string|null $recipients
   *   (optional) A list of recipient emails.
   *
   * @throws \Exception
   *   Thrown when the count doesn't match the actual number.
   */
  #[Then(':count e-mail(s) should have been sent')]
  #[Then(':count e-mail(s) should have been sent to recipients :recipients')]
  public function assertNumberOfEmailSent(int $count, ?string $recipients = NULL): void {
    $mails = $this->getMails();
    if (count($mails) != $count) {
      throw new \Exception("Invalid number of e-mail sent. Expected $count, sent " . count($mails));
    }
    if ($recipients) {
      $expected_recipients = $this->explodeDelimitedSeparatedStepArgument($recipients);
      $actual_recipients = array_unique(array_map(
        fn(array $mail): string => $mail['to'],
        $mails,
      ));
      sort($expected_recipients);
      sort($actual_recipients);
      if ($expected_recipients !== $actual_recipients) {
        throw new ExpectationException("Expected recipients $recipients. Got " . implode(', ', $actual_recipients), $this->getSession()->getDriver());
      }
    }
  }

  /**
   * Searches the delete confirmation link from an email and navigates to it.
   *
   * @param string $user
   *   The user name.
   *
   * @throws \Exception
   *   Thrown when a user is not found.
   */
  #[When('I click the delete confirmation link for (the user ):user from the last email')]
  public function clickDeleteConfirmationLink($user): void {
    $user = $this->getUserByName($user);
    if (empty($user)) {
      throw new \Exception("User {$user->getAccountName()} was not found.");
    }

    $mail = $user->getEmail();
    // In plain text, the urls are encapsulated in "[]" or are plain into the
    // text. The end character is either the "]" or a space.
    $pattern = '#https?://[^/].*/' . $user->id() . '/cancel/confirm/\d+[^\s\[\]]*#';
    $no_mail_message = "No email was sent to $mail containing the delete confirmation link.";
    $no_match_message = "The email sent to $mail did not contain a valid delete confirmation link.";
    $this->assertMailLinkMatchingPattern($pattern, $mail, $no_mail_message, $no_match_message, NULL);
  }

  /**
   * Clicks the comment link found in an email.
   *
   * @param string $user
   *   The user name the email was sent to.
   *
   * @throws \Exception
   *   Thrown when a user is not found.
   */
  #[When('I click the comment link from the last email sent to :user')]
  public function clickCommentLinkInEmail($user): void {
    $user = $this->getUserByName($user);
    if (empty($user)) {
      throw new \Exception("User {$user->getAccountName()} was not found.");
    }

    $mail = $user->getEmail();
    $pattern = '!https?://[^ \"\]]*?#comment-\d+[^ \"\]]*?!';
    $no_mail_message = "No email was sent to $mail containing a comment anchored link.";
    $no_match_message = "The email sent to $mail did not contain a valid comment anchored link.";
    $this->assertMailLinkMatchingPattern($pattern, $mail, $no_mail_message, $no_match_message, NULL);
  }

  /**
   * Clicks on a link for an attachment in a contact form confirmation mail.
   *
   * Note that if multiple messages have been sent to the same user, all of them
   * will be checked but only the first instance of the link matching the
   * pattern will be clicked.
   *
   * @param string $filename
   *   The name of the file that was attached to the contact form.
   * @param string $mail
   *   The recipient's mail.
   *
   * @throws \Exception
   *   If no mail was found linked to the given email or if the link to the file
   *   attachment was not found.
   */
  #[When('I click the link for the :filename attachment in the contact form email sent to :mail')]
  public function clickAttachmentInContactFormConfirmationMail(string $filename, string $mail): void {
    // In case multiple files with the same name were uploaded, the File module
    // will append a number to the file. Account for this.
    $extension = pathinfo($filename, PATHINFO_EXTENSION);
    $name = pathinfo($filename, PATHINFO_FILENAME);
    $filename_pattern = $name . '(_\d+)?.' . $extension;

    $pattern = '#https?://[^/].*?/contact_form/\d{4}-\d{2}/' . $filename_pattern . '#';
    $no_mail_message = "No mail was found to have been sent to '$mail'.";
    $no_match_message = "No link to an attachment with filename '$filename' was found in the emails sent to '$mail'.";

    $this->assertMailLinkMatchingPattern($pattern, $mail, $no_mail_message, $no_match_message, 'contact_form_submission');
  }

  /**
   * Clicks on the first link from an email with give subject and recipient.
   *
   * @param string $username_or_email
   *   Either the username or an email.
   * @param string $subject
   *   The email subject.
   */
  #[When('I click the link from email sent to :username_or_email with subject :subject')]
  public function clickLinkInEmail(string $username_or_email, string $subject): void {
    if ($account = user_load_by_name($username_or_email)) {
      $recipient = $account->getEmail();
    }
    elseif (\Drupal::getContainer()->get('email.validator')->isValid($username_or_email)) {
      $recipient = $username_or_email;
    }
    else {
      throw new \InvalidArgumentException("'$username_or_email' is neither a valid username, nor a valid email.");
    }
    $no_mail_message = "No email with '$subject' subject was sent to $recipient";
    $no_match_message = "The email sent to $recipient did not contain a link.";
    $this->assertMailLinkMatchingPattern('!https?://[^\s\]]*!', $recipient, $no_mail_message, $no_match_message, NULL);
  }

  /**
   * Asserts that no email has been sent to a given recipient.
   *
   * @param string $username_or_email
   *   A valid user name or a valid email.
   */
  #[Then('no email has been sent to :username_or_email')]
  public function noEmailSentToRecipient(string $username_or_email): void {
    if ($account = user_load_by_name($username_or_email)) {
      /** @var \Drupal\user\UserInterface $account */
      $recipient = $account->getEmail();
    }
    elseif (\Drupal::service('email.validator')->isValid($username_or_email)) {
      $recipient = $username_or_email;
    }
    else {
      throw new \InvalidArgumentException("Invalid '$username_or_email' value passed. Should be either a valid username or a vaid email address.");
    }
    $this->assertCondition(empty($this->getMails(['to' => $recipient])), "No email should have been sent to '$username_or_email'.");
  }

  /**
   * Clicks on link given its text in an HTML email.
   *
   * @param string $link_text
   *   The link text.
   * @param string $recipient
   *   The recipient's email.
   * @param string $subject
   *   The email subject.
   */
  #[When('I click the :link_text link in the email sent to :recipent having the subject :subject')]
  public function clickLinkInHtmlMail(string $link_text, string $recipient, string $subject): void {
    $mail = $this->getEmailsBySubjectAndMail($subject, $recipient)[0];
    $body = (string) $mail['body'];
    $crawler = new Crawler($body);
    $link = $crawler->filterXPath('//a[text()="' . $link_text . '"]');
    $this->visitPath($link->getNode(0)->attributes['href']->nodeValue);
  }

}
