<?php

declare(strict_types=1);

namespace Drupal\joinup_user;

use Drupal\Core\DestructableInterface;
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
    'comment' => ['uid', NULL],
    'node' => ['uid', 'revision_uid'],
  ];

  /**
   * Acts when a user account is being cancelled.
   *
   * @param \Drupal\user\UserInterface $account
   *   The user account.
   */
  public function onUserDelete(UserInterface $account): void;

}
