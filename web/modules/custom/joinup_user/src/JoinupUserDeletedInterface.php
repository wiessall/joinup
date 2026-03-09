<?php

declare(strict_types=1);

namespace Drupal\joinup_user;

use Drupal\Core\DestructableInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\user\EntityOwnerInterface;
use Drupal\user\UserInterface;

/**
 * Interface of 'joinup_user.deleted' service.
 */
interface JoinupUserDeletedInterface extends DestructableInterface {

  /**
   * Table storing the deleted users names.
   */
  public const string TABLE_USER = 'joinup_user_deleted_user';

  /**
   * Table storing the deleted users content.
   */
  public const string TABLE_USER_CONTENT = 'joinup_user_deleted_user_content';

  /**
   * Relevant entity types authored by a deleted user.
   *
   * A user may author different entity types (media, file, etc.), but we're
   * only interested in the entities authored by a deleted user which are
   * publicly relevant.
   *
   * @var array<array-key, array{non-empty-string, ?string}>
   *   Associative array. Entity types are keys and each value is a tuple with
   *   the entity type owner key and the revision user key. If the entity is not
   *   revisionable, NULL is set as revision user key.
   */
  public const array ENTITY_TYPES = [
    'assessment' => ['uid', NULL],
    'comment' => ['uid', NULL],
    'node' => ['uid', 'revision_uid'],
  ];

  /**
   * Returns the ID of user account which authored a given entity.
   *
   * @param \Drupal\user\EntityOwnerInterface $entity
   *   The entity authored by the deleted user.
   *
   * @return int|null
   *   The ID of user account which authored a given entity.
   */
  public function getContentDeletedAuthor(EntityOwnerInterface $entity): ?int;

  /**
   * Checks whether an account has been registered as deleted.
   *
   * @param \Drupal\user\UserInterface $account
   *   The user account.
   *
   * @return bool
   *   Whether an account has been registered as deleted.
   */
  public function isAccountDeleted(AccountInterface $account): bool;

  /**
   * Computes the user's full name.
   *
   * @param \Drupal\user\UserInterface $account
   *   The user account.
   */
  public function getFullName(AccountInterface $account): string;

  /**
   * Acts when a user account is being cancelled.
   *
   * @param \Drupal\user\UserInterface $account
   *   The user account.
   */
  public function onUserDelete(UserInterface $account): void;

  /**
   * Creates a pseudo-user based only on its deleted account UID.
   *
   * @param int|string $uid
   *   The ID of the user that has been deleted.
   *
   * @return \Drupal\user\UserInterface
   *   A mocked user entity instance.
   */
  public function getDeletedUserPseudoAccount(int|string $uid): UserInterface;

  /**
   * Cleans-up the tables when an entity is being deleted.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity being deleted.
   */
  public function onEntityDelete(EntityInterface $entity): void;

}
