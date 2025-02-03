<?php

declare(strict_types=1);

namespace Drupal\joinup_user;

use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\user\UserInterface;

/**
 * Default implementation of 'joinup_user.fullname' service.
 */
class JoinupUserFullname implements JoinupUserFullnameInterface {

  use StringTranslationTrait;

  public function __construct(
    protected Connection $database,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly CacheTagsInvalidatorInterface $cacheTagsInvalidator,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function onUserInsert(UserInterface $account): void {
    $this->updateTable($account);
  }

  /**
   * {@inheritdoc}
   */
  public function onUserUpdate(UserInterface $account): void {
    $original = $account->original;
    assert($original instanceof UserInterface);
    if (
      $original->get('field_user_first_name')->value !== $account->get('field_user_first_name')->value ||
      $original->get('field_user_family_name')->value !== $account->get('field_user_family_name')->value ||
      $original->getAccountName() !== $account->getAccountName()
    ) {
      $this->updateTable($account);
      $this->cacheTagsInvalidator->invalidateTags($account->getCacheTags());
    }
  }

  /**
   * {@inheritdoc}
   */
  public function onUserDelete(UserInterface $account): void {
    $this->database->update(self::TABLE)
      ->fields(['deleted' => 1])
      ->condition('uid', $account->id())
      ->execute();
    $this->cacheTagsInvalidator->invalidateTags($account->getCacheTags());
  }

  /**
   * {@inheritdoc}
   */
  public function getFullname(AccountInterface $account): string {
    $uid = $account->id();

    if ($account = $this->entityTypeManager->getStorage('user')->load($uid)) {
      // Pass to string translation so that we can also localize the full name
      // for languages where the family name comes first (e.g., Hungarian).
      $fullname = trim((string) $this->t('@first_name @family_name', [
        '@first_name' => $account->get('field_user_first_name')->value,
        '@family_name' => $account->get('field_user_family_name')->value,
      ], ['context' => 'fullname']));
    }
    else {
      // Deleted user?
      $fullname = $this->database->select(self::TABLE)
        ->fields(self::TABLE, ['fullname'])
        ->condition('uid', $uid)
        ->condition('deleted', 1)
        ->execute()
        ->fetchField();
    }

    // Default to the account name.
    return $fullname ?: $account->getAccountName();
  }

  /**
   * {@inheritdoc}
   */
  public function isUserDeleted(UserInterface|int|string $accountOrAccountId): bool {
    $uid = $accountOrAccountId instanceof UserInterface ? $accountOrAccountId->id() : $accountOrAccountId;
    return (bool) $this->database->select(self::TABLE)
      ->fields(self::TABLE, ['deleted'])
      ->condition('uid', $uid)
      ->execute()
      ->fetchField();
  }

  /**
   * {@inheritdoc}
   */
  public function getDeletedUser(int|string $uid): UserInterface {
    return $this->entityTypeManager->getStorage('user')->create(['uid' => $uid]);
  }

  /**
   * Updates the user full name table.
   *
   * @param \Drupal\user\UserInterface $account
   *   The user account entity instance.
   */
  protected function updateTable(UserInterface $account): void {
    $this->database->merge(self::TABLE)
      ->keys(['uid' => $account->id()])
      ->fields(['fullname' => $account->getDisplayName()])
      ->execute();
  }

}
