<?php

declare(strict_types=1);

namespace Drupal\joinup_core;

use Drupal\Component\Utility\Crypt;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\user\UserInterface;

/**
 * Locates the backend moderator service account by identity hash.
 */
class BackendModerator {

  const string SERVICE_ACCOUNT_HASH = 'NKIl5O1OD62pfz97xaZYrin5pSKes9PmzFwcRgVyf0M';

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Returns the backend moderator service account.
   *
   * @return \Drupal\user\UserInterface
   *   The service account.
   *
   * @throws \Exception
   *   If the service account cannot be found.
   */
  public function getServiceAccount(): UserInterface {
    $storage = $this->entityTypeManager->getStorage('user');
    $uids = $storage->getQuery()
      ->condition('roles', 'moderator')
      ->sort('created', 'DESC')
      ->accessCheck(FALSE)
      ->execute();

    foreach ($uids as $uid) {
      $user = $storage->load($uid);
      if (!$user instanceof UserInterface) {
        continue;
      }
      $hash = Crypt::hashBase64($user->id() . $user->uuid() . $user->getCreatedTime());
      if ($hash === self::SERVICE_ACCOUNT_HASH) {
        return $user;
      }
    }

    throw new \Exception('Service account not found');
  }

}
