<?php

declare(strict_types=1);

namespace Drupal\joinup_user;

use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\user\UserInterface;

/**
 * Service to assist with sending notifications about actions taken on users.
 */
class JoinupUserNotificationHelper implements JoinupUserNotificationHelperInterface {

  use StringTranslationTrait;

  public function __construct(
    protected readonly AccountInterface $currentUser,
    protected readonly MessengerInterface $messenger,
    protected readonly MailManagerInterface $mailManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function notifyOnAccountChange(UserInterface $user): void {
    // Drupal allows accounts without E-mail when the account is created by an
    // administrator. We skip them because they cannot receive notifications.
    if (!$user->getEmail()) {
      return;
    }

    // A notification should only be sent when the user account is changed by
    // another user.
    if ($user->isAnonymous() || $this->currentUser->isAnonymous() || $user->id() == $this->currentUser->id()) {
      return;
    }

    // Inform the user that their account was updated.
    $this->mailManager->mail(
      'joinup_user',
      'email_admin_update',
      $user->getEmail(),
      $user->getPreferredLangcode(),
      ['account' => $user]
    );

    $this->messenger->addStatus($this->t('The user has been notified that their account has been updated.'));
  }

}
