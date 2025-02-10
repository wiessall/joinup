<?php

declare(strict_types=1);

namespace Drupal\joinup_user;

use Drupal\Core\DestructableInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\user\UserInterface;

/**
 * Interface of 'joinup_user.full_name' service.
 */
interface JoinupUserFullNameInterface extends DestructableInterface {

  /**
   * Table storing the relation between UID and user full name.
   */
  public const string TABLE = 'joinup_user_full_name';

  /**
   * Acts after a new user has been inserted.
   *
   * @param \Drupal\user\UserInterface $account
   *   The user account.
   */
  public function onUserInsert(UserInterface $account): void;

  /**
   * Acts after a user has been updated.
   *
   * @param \Drupal\user\UserInterface $account
   *   The user account.
   */
  public function onUserUpdate(UserInterface $account): void;

  /**
   * Acts after a user has been deleted.
   *
   * @param \Drupal\user\UserInterface $account
   *   The user account.
   */
  public function onUserDelete(UserInterface $account): void;

  /**
   * Computes the user's full name.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account.
   */
  public function getFullName(AccountInterface $account): string;

  /**
   * Checks whether a user has been deleted.
   *
   * @param int|string $uid
   *   The user account ID.
   *
   * @return bool
   *   Whether the user has been deleted.
   */
  public function isUserDeleted(int|string $uid): bool;

  /**
   * Creates a pseudo-user based only on its deleted account UID.
   *
   * @param int|string $uid
   *   The ID of the user that has been deleted.
   *
   * @return \Drupal\user\UserInterface
   *   A mocked user entity instance.
   */
  public function getDeletedUser(int|string $uid): UserInterface;

}
