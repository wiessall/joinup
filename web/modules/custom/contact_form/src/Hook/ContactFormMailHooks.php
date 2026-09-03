<?php

declare(strict_types=1);

namespace Drupal\contact_form\Hook;

use Drupal\Component\Render\PlainTextOutput;
use Drupal\Component\Utility\EmailValidatorInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\joinup_notification\MailContentTypeHeader;

/**
 * Hook implementations for contact form mail.
 */
final class ContactFormMailHooks {

  use StringTranslationTrait;

  public function __construct(
    protected readonly ConfigFactoryInterface $configFactory,
    protected readonly EmailValidatorInterface $emailValidator,
  ) {}

  /**
   * Implements hook_mail_alter().
   *
   * Alter sender of outgoing mails sent from the contact form so they will be
   * handled by support desk software and turned into tickets.
   *
   * @todo This doesn't belong in the Interoperable Europe Portal distribution and should be removed.
   *
   * @see https://citnet.tech.ec.europa.eu/CITnet/jira/browse/ISAICP-3988
   */
  #[Hook('mail_alter')]
  public function mailAlter(array &$message): void {
    if ($message['key'] === 'contact_form_submission') {
      $from = $this->configFactory->get('contact_form.settings')->get('contact_form_from');
      if (!empty($from) && $this->emailValidator->isValid($from)) {
        $message['from'] = $from;
      }
    }
  }

  /**
   * Implements hook_mail().
   *
   * Send a notification to the contact form submitter.
   */
  #[Hook('mail')]
  public function mail(string $key, array &$message, array $params): void {
    if ($key !== 'contact_form_sender') {
      return;
    }

    /** @var \Drupal\message\MessageInterface $entity */
    $entity = $params['message'];
    $name = "{$entity->field_contact_first_name->value} {$entity->field_contact_last_name->value}";

    $message['subject'] = $this->t('Interoperable Europe Portal: Contact form notification');

    $message['body'][] = $this->t('Dear @name', ['@name' => $name]);
    $message['body'][] = $this->t('Thank you for submitting your message to the Interoperable Europe Portal Support.');

    if ($entity->field_contact_copy->value) {
      $message['body'][] = $this->t('This is the message you submitted through the online form:');
      $message['body'][] = $this->t(
        'Subject: %subject', [
          '%subject' => PlainTextOutput::renderFromHtml($entity->field_contact_subject->value),
        ]
      );
      $message['body'][] = $this->t('Content:');
      $message['body'][] = '<blockquote>';
      $message['body'][] = check_markup($entity->field_contact_message->value, $entity->field_contact_message->format);
      $message['body'][] = '</blockquote>';
      $message['body'][] = $this->t('Thank you for your interest, we will reply as soon as possible.');
    }
    else {
      $message['body'][] = $this->t('We will reply as soon as possible.');
    }

    $message['body'][] = $this->t('Kind regards,');
    $message['body'][] = $this->t('The Portal Support Team');

    // Send as HTML.
    $message['headers']['Content-Type'] = MailContentTypeHeader::TextHtml->value;
  }

}
