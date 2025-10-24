<?php

declare(strict_types=1);

namespace Drupal\joinup_api;

use Drupal\Component\Render\PlainTextOutput;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Utility\Token;
use Drupal\oauth_client\Entity\NotificationType;
use Drupal\oauth_client\Entity\OauthClientRequestInterface;
use Drupal\oauth_client\NotificationSenderInterface;
use Drupal\user\Entity\Role;

/**
 * Sends OAuth client notification emails as plain text.
 */
final class NotificationSender implements NotificationSenderInterface {

  /**
   * Cached OAuth client settings.
   *
   * @var array|null
   */
  protected ?array $oauthClientSettings;

  /**
   * Constructs a NotificationSender object.
   */
  public function __construct(
    private readonly MailManagerInterface $mailManager,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly Token $token,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly LanguageManagerInterface $languageManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function handleRequestNotification(OauthClientRequestInterface $entity): void {
    if (!$this->isActionEnabled(NotificationType::Request)) {
      return;
    }

    if ($uids = $this->getManagerUids()) {
      $this->sendEmailToUsers($entity, $uids, NotificationType::Request);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function handleApprovalNotification(OauthClientRequestInterface $entity): void {
    if (!$this->isActionEnabled(NotificationType::Approve)) {
      return;
    }
    $requestUser = $entity->getUser();
    $this->sendEmailToUsers($entity, [$requestUser->id()], NotificationType::Approve);
  }

  /**
   * {@inheritdoc}
   */
  public function handleRejectionNotification(OauthClientRequestInterface $entity): void {
    if (!$this->isActionEnabled(NotificationType::Reject)) {
      return;
    }
    $requestUser = $entity->getUser();
    $this->sendEmailToUsers($entity, [$requestUser->id()], NotificationType::Reject);
  }

  /**
   * Checks if a specific action is enabled in the configuration.
   *
   * @param \Drupal\oauth_client\Entity\NotificationType $action
   *   The action enum (Request, Approve, Reject).
   *
   * @return bool
   *   TRUE if the action is enabled, FALSE otherwise.
   */
  protected function isActionEnabled(NotificationType $action): bool {
    $settings = $this->getActionSettings($action);
    return !empty($settings['enabled']);
  }

  /**
   * Retrieves action settings from configuration.
   *
   * @param \Drupal\oauth_client\Entity\NotificationType $action
   *   The action enum (Request, Approve, Reject).
   *
   * @return array{enabled: bool, subject: string, body: string}
   *   An associative array containing 'enabled', 'subject', and 'body' keys.
   */
  protected function getActionSettings(NotificationType $action): array {
    if (empty($this->oauthClientSettings)) {
      $this->oauthClientSettings = $this->configFactory->get('oauth_client.settings')->getRawData();
    }

    return $this->oauthClientSettings['notifications'][$action->value];
  }

  /**
   * Fetches all users with the 'manage oauth clients' permission.
   *
   * @return int[]
   *   An array of user entities with the 'manage oauth clients' permission.
   */
  protected function getManagerUids(): array {
    $roleStorage = $this->entityTypeManager->getStorage('user_role');
    $rolesByPermission = array_filter($roleStorage->loadMultiple(), function (Role $role): bool {
      return $role->hasPermission('manage oauth clients');
    });

    if (empty($rolesByPermission)) {
      return [];
    }

    $roleIds = array_keys($rolesByPermission);
    $userStorage = $this->entityTypeManager->getStorage('user');
    $query = $userStorage->getQuery()
      ->accessCheck(FALSE)
      ->condition('status', 1)
      ->condition('roles', $roleIds, 'IN');
    return $query->execute();
  }

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
