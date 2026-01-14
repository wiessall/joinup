<?php

declare(strict_types=1);

namespace Drupal\joinup_api;

use Drupal\oauth_client\Entity\NotificationType;
use Drupal\oauth_client\Entity\OauthClientRequestInterface;
use Drupal\oauth_client\NotificationSender as BaseNotificationSender;

/**
 * Sends OAuth client notification emails as HTML.
 *
 * Extends the base NotificationSender to send HTML emails.
 */
final class NotificationSender extends BaseNotificationSender {

  /**
   * Sends email to a list of users with token replacements.
   *
   * Uses joinup_api mail key to send HTML emails.
   *
   * @param \Drupal\oauth_client\Entity\OauthClientRequestInterface $entity
   *   The OAuth client request entity.
   * @param int[] $uids
   *   An array of user IDs to send the email to.
   * @param \Drupal\oauth_client\Entity\NotificationType $action
   *   The action triggering the email.
   */
  protected function sendEmailToUsers(OauthClientRequestInterface $entity, array $uids, NotificationType $action): void {
    $requestType = $this->getRequestType($entity);
    if (!$requestType) {
      return;
    }

    $subjectTemplate = $requestType->getNotificationSetting($action, 'subject') ?? '';
    $bodyTemplate = $requestType->getNotificationSetting($action, 'body') ?? '';
    $userStorage = $this->entityTypeManager->getStorage('user');
    $accounts = $userStorage->loadMultiple($uids);
    foreach ($accounts as $account) {
      $data = [
        'oauth_client_request' => $entity,
        'user' => $account,
        'oauth_client' => NULL,
      ];

      $subject = $this->token->replace($subjectTemplate, $data, ['clear' => TRUE]);
      $body = $this->token->replace($bodyTemplate, $data, ['clear' => TRUE]);
      $to = $account->getEmail();
      $langcode = $account->getPreferredLangcode() ?: $this->languageManager->getDefaultLanguage()->getId();

      $params = [
        'context' => [
          'subject' => $subject,
          'message' => $body,
        ],
      ];
      $this->mailManager->mail('joinup_api', 'oauth_client_notification', $to, $langcode, $params);
    }
  }

}
