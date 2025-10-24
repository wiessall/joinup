<?php

declare(strict_types=1);

namespace Drupal\joinup_api;

use Drupal\Component\Render\PlainTextOutput;
use Drupal\oauth_client\Entity\NotificationType;
use Drupal\oauth_client\Entity\OauthClientRequestInterface;
use Drupal\oauth_client\NotificationSender as BaseNotificationSender;

/**
 * Sends OAuth client notification emails as plain text.
 *
 * Extends the base NotificationSender to send plain text emails instead of
 * HTML.
 */
final class NotificationSender extends BaseNotificationSender {

  /**
   * Sends email to a list of users with token replacements.
   *
   * Uses joinup_api mail key to send plain text emails.
   *
   * @param \Drupal\oauth_client\Entity\OauthClientRequestInterface $entity
   *   The OAuth client request entity.
   * @param int[] $uids
   *   An array of user IDs to send the email to.
   * @param \Drupal\oauth_client\Entity\NotificationType $action
   *   The action triggering the email.
   */
  protected function sendEmailToUsers(OauthClientRequestInterface $entity, array $uids, NotificationType $action): void {
    $notification = $this->getActionSettings($action);

    $replacementsContext = [
      'user' => $entity->getUser(),
      'oauth_client_request' => $entity,
    ];
    $subject = $this->token->replace($notification['subject'], $replacementsContext, ['clear' => TRUE]);
    $body = $this->token->replace($notification['body'], $replacementsContext, ['clear' => TRUE]);

    // Strip HTML from subject and body to ensure plain text.
    $subject = PlainTextOutput::renderFromHtml($subject);
    $body = PlainTextOutput::renderFromHtml($body);

    $userStorage = $this->entityTypeManager->getStorage('user');
    $accounts = $userStorage->loadMultiple($uids);
    foreach ($accounts as $account) {
      $to = $account->getEmail();
      $langcode = $account->getPreferredLangcode() ?: $this->languageManager->getDefaultLanguage()->getId();

      $this->mailManager->mail('joinup_api', 'oauth_client_notification', $to, $langcode, [
        'subject' => $subject,
        'body' => $body,
      ]);
    }
  }

}
