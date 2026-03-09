<?php

declare(strict_types=1);

namespace Drupal\joinup\Traits;

use Behat\Mink\Exception\ExpectationException;
use Drupal\Component\Render\MarkupInterface;
use Drupal\user\UserInterface;

/**
 * Contains utility methods.
 */
trait MailCollectorTrait {

  /**
   * Formats a mail body to a simple string.
   *
   * @param \Drupal\Component\Render\MarkupInterface $markup
   *   The mail body markup.
   *
   * @return string
   *   The simplified mail body.
   */
  protected function formatMailBodyMarkup(MarkupInterface $markup): string {
    $mail_body = $this->formatMailBodyText((string) $markup);
    // For formattable markups, we also need to:
    // - undo the encoding done by Twig to quotes;
    // - remove the HTML to simplify the matching on the body text.
    // @see vendor/twig/twig/lib/Twig/Extension/Core.php:1034
    $mail_body = strip_tags($mail_body);
    return htmlspecialchars_decode($mail_body, ENT_QUOTES | ENT_SUBSTITUTE);
  }

  /**
   * Strips and formats a mail body to a format used in tests.
   *
   * @param string $mail_body
   *   The mail body.
   *
   * @return string
   *   The formatted.
   */
  protected function formatMailBodyText(string $mail_body): string {
    // \Drupal\Core\Mail\Plugin\Mail\PhpMail::format() automatically wraps the
    // mail body line to a certain amount of characters (77 by default).
    // Spaces are also removed.
    // @see \Drupal\Core\Mail\Plugin\Mail\PhpMail::format()
    $mail_body = str_replace("\r\n", "\n", $mail_body);
    $mail_body = preg_replace("/[\n\s\t]+/", ' ', $mail_body);
    return trim($mail_body);
  }

  /**
   * Retrieves emails sent to the user from the email collection.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user for which to retrieve emails.
   *
   * @return array
   *   An array of mails as stored in the email collector.
   *
   * @throws \Exception
   *   Thrown if no emails have been sent.
   */
  protected function getUserMails(UserInterface $user): array {
    $mails = $this->getMails();
    if (empty($mails)) {
      throw new \Exception('No mail was sent.');
    }

    $user_email = $user->getEmail();
    return array_filter($mails, function (array $mail) use ($user_email) {
      return $mail['to'] === $user_email;
    });
  }

  /**
   * Fetches the emails sent given criteria.
   *
   * @param string $subject
   *   The subject of the email sent.
   * @param string $recipient
   *   The email of the recipient.
   * @param bool $strict
   *   When set to TRUE will throw an exception if no mails are found with the
   *   given subject and mail. Defaults to TRUE.
   * @param bool $search_recipient_in_list
   *   If TRUE, the email recipient contains recipients other than $recipient.
   *
   * @return array
   *   An array of emails found.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   *   When no emails are found with the given subject and/or recipient.
   */
  protected function getEmailsBySubjectAndMail(string $subject, string $recipient, bool $strict = TRUE, bool $search_recipient_in_list = FALSE): array {
    $emails_found = $this->getMails(['to' => $recipient, 'subject' => $subject], $search_recipient_in_list);
    if (empty($emails_found) && $strict) {
      throw new ExpectationException("No emails found sent to {$recipient} with subject '{$subject}'.", $this->getSession()->getDriver());
    }
    return $emails_found;
  }

  /**
   * Gets an array containing all emails sent during this test case.
   *
   * Not that this method is 98% copied from AssertMailTrait::getMails(), with
   * two exceptions:
   * - It uses \Drupal::state() to get the state service.
   * - It clears the state service cache before doing any processing.
   *
   * @param array $filter
   *   An array containing key/value pairs used to filter the emails that are
   *   returned.
   * @param bool $search_recipient_in_list
   *   If TRUE, the email recipient contains recipients other than $recipient.
   *
   * @return array[]
   *   An array containing email messages captured during the current test.
   *
   * @see \Drupal\Core\Test\AssertMailTrait::getMails()
   */
  protected function getMails(array $filter = [], bool $search_recipient_in_list = FALSE): array {
    \Drupal::state()->resetCache();
    $captured_emails = \Drupal::state()->get('system.test_mail_collector', []);
    $filtered_emails = [];

    foreach ($captured_emails as $message) {
      foreach ($filter as $key => $value) {
        if (!$search_recipient_in_list && (!isset($message[$key]) || $message[$key] != $value)) {
          continue 2;
        }
        // This allows to check is the recipient is in a list.
        if ($key === 'to' && $search_recipient_in_list && !str_contains($message[$key], $value)) {
          continue 2;
        }
      }
      $filtered_emails[] = $message;
    }

    return $filtered_emails;
  }

  /**
   * Clicks on a link matching a pattern given the email text and the recipient.
   *
   * Note that if multiple messages have been sent to the same user, all of them
   * will be checked but only the first instance of the link matching the
   * pattern will be clicked.
   *
   * @param string $pattern
   *   The pattern that the link should match to.
   * @param string $mail
   *   The recipient's mail.
   * @param string $no_mail_message
   *   The exception message to show when no mail was found that matches the
   *   given e-mail address.
   * @param string $no_match_message
   *   The exception message to show when the given pattern was not found in the
   *   messages sent to the given e-mail address.
   * @param string|null $template
   *   (optional) Message template to filter the messages by.
   *
   * @throws \Exception
   *   If no mail was found linked to the given email or if the pattern did not
   *   find any matches.
   */
  protected function assertMailLinkMatchingPattern(string $pattern, string $mail, string $no_mail_message, string $no_match_message, ?string $template = NULL): void {
    $found = FALSE;
    foreach ($this->getMails() as $mail_sent) {
      // Optionally filter by mail template.
      if (!empty($template) && $mail_sent['key'] !== $template) {
        continue;
      }

      // Filter out messages that do not match the given e-mail address.
      if (!str_contains($mail_sent['to'], $mail)) {
        continue;
      }

      // This is a flag that a message was found to have been sent to the user.
      $found = TRUE;
      if (!preg_match($pattern, $mail_sent['plain'], $match)) {
        continue;
      }

      $link = strpos($pattern, '?<url>') === FALSE ? $match[0] : $match['url'];
      // Remove trailing punctuation that might have been captured from sentence
      // endings when HTML is converted to plain text.
      $link = rtrim($link, '.,;!?');
      $this->visitPath($link);
      return;
    }

    if ($found) {
      throw new \Exception($no_match_message);
    }
    throw new \Exception($no_mail_message);
  }

}
