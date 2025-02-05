<?php

declare(strict_types=1);

namespace Drupal\joinup_user;

use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Default implementation of 'joinup_user.full_name' service.
 */
class JoinupUserFullName implements JoinupUserFullNameInterface {

  public function __construct(
    protected Connection $database,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly CacheTagsInvalidatorInterface $cacheTagsInvalidator,
    #[Autowire(service: 'logger.channel.user')]
    protected readonly LoggerChannelInterface $logger,
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
  public function getFullName(AccountInterface $account): string {
    $fullName = $this->database->select(self::TABLE)
      ->fields(self::TABLE, ['full_name'])
      ->condition('uid', $account->id())
      ->execute()
      ->fetchField();

    if (!$fullName) {
      $this->logger->error('User with ID @uid is missing a joinup_user_full_name table entry', [
        '@uid' => $account->id(),
      ]);
      return '';
    }

    return $fullName;
  }

  /**
   * {@inheritdoc}
   */
  public function isUserDeleted(int|string $uid): bool {
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
    $fullName = trim($account->get('field_user_first_name')->value . ' ' . $account->get('field_user_family_name')->value);
    $fullName = $fullName ?: $account->getAccountName();
    $this->database->merge(self::TABLE)
      ->keys(['uid' => $account->id()])
      ->fields(['full_name' => $fullName])
      ->execute();
  }

}
