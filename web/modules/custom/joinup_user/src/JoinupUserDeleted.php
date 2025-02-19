<?php

declare(strict_types=1);

namespace Drupal\joinup_user;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\RevisionLogInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\TypedData\TranslatableInterface;
use Drupal\user\EntityOwnerInterface;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Default implementation of 'joinup_user.deleted' service.
 */
class JoinupUserDeleted implements JoinupUserDeletedInterface {

  /**
   * List of deleted user accounts.
   *
   * @var \Drupal\user\UserInterface[]
   */
  protected array $stack = [];

  public function __construct(
    protected readonly Connection $database,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected RequestStack $requestStack,
    #[Autowire(service: 'logger.channel.user')]
    protected readonly LoggerChannelInterface $logger,
    // Use a memory cache bin instead of an internal class property to be able
    // to invalidate the memory cache based on cache tags.
    #[Autowire(service: 'joinup_user.memory_cache')]
    protected CacheBackendInterface $cache,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getContentDeletedAuthor(EntityOwnerInterface $entity): ?int {
    return (int) $this->database->select(JoinupUserDeletedInterface::TABLE_USER_CONTENT)
      ->fields(JoinupUserDeletedInterface::TABLE_USER_CONTENT)
      ->condition('entity_type', $entity->getEntityTypeId())
      ->condition('entity_id', $entity->id())
      ->execute()
      ->fetchField() ?: NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function isAccountDeleted(AccountInterface $account): bool {
    return (bool) $this->database->select(JoinupUserDeletedInterface::TABLE_USER)
      ->fields(JoinupUserDeletedInterface::TABLE_USER)
      ->condition('uid', $account->id())
      ->countQuery()
      ->execute()
      ->fetchField();
  }

  /**
   * {@inheritdoc}
   */
  public function getFullName(AccountInterface $account): string {
    $cid = "full_name:{$account->id()}";
    if ($cached = $this->cache->get($cid)) {
      return $cached->data;
    }

    if ($this->isAccountDeleted($account)) {
      $fullName = $this->database->select(self::TABLE_USER)
        ->fields(self::TABLE_USER, ['full_name'])
        ->condition('uid', $account->id())
        ->execute()
        ->fetchField();

      if ($fullName === FALSE) {
        $this->logger->error('User with ID @uid is missing a @table table entry', [
          '@table' => self::TABLE_USER,
          '@uid' => $account->id(),
        ]);
        // Don't cache an erroneous value.
        return '';
      }
    }
    else {
      $account = $account instanceof UserInterface ? $account : $this->entityTypeManager->getStorage('user')->load($account->id());
      $fullName = trim($account->get('field_user_first_name')->value . ' ' . $account->get('field_user_family_name')->value);
      $fullName = $fullName ?: $account->getAccountName();
    }
    $this->cache->set($cid, $fullName, Cache::PERMANENT, $account->getCacheTagsToInvalidate());

    return $fullName;
  }

  /**
   * {@inheritdoc}
   */
  public function onUserDelete(UserInterface $account): void {
    if (!isset($this->stack[$account->id()])) {
      $this->stack[$account->id()] = $account;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function destruct(): void {
    while ($this->stack) {
      $account = array_shift($this->stack);
      if (!$content = $this->getDeletedUserContent($account)) {
        continue;
      }

      // Prevent geocoding.
      $this->requestStack->getCurrentRequest()->attributes->set('geocoder_presave_disabled', TRUE);

      $this->database->insert(JoinupUserDeletedInterface::TABLE_USER)->fields([
        'uid' => $account->id(),
        'full_name' => $account->getDisplayName(),
      ])->execute();

      foreach ($content as $entityTypeId => $ids) {
        // Replaces comment_user_predelete() and node_user_predelete().
        // @see joinup_user_module_implements_alter()
        $this->setAnonymousAsOwner($account, $entityTypeId, $ids);

        foreach (array_unique($ids) as $id) {
          $this->database->insert(JoinupUserDeletedInterface::TABLE_USER_CONTENT)->fields([
            'uid' => $account->id(),
            'entity_type' => $entityTypeId,
            'entity_id' => $id,
          ])->execute();
        }
      }

      // Restore geocoding.
      $this->requestStack->getCurrentRequest()->attributes->remove('geocoder_presave_disabled');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getDeletedUserPseudoAccount(int|string $uid): UserInterface {
    return $this->entityTypeManager->getStorage('user')->create(['uid' => $uid]);
  }

  /**
   * {@inheritdoc}
   */
  public function onEntityDelete(EntityInterface $entity): void {
    if (!isset(self::ENTITY_TYPES[$entity->getEntityTypeId()])) {
      return;
    }

    $deleted = $this->database->delete(self::TABLE_USER_CONTENT)
      ->condition('entity_type', $entity->getEntityTypeId())
      ->condition('entity_id', $entity->id())
      ->execute();

    if ($deleted) {
      // Cleanup entries from {joinup_user_deleted_user} that are left without
      // a corresponding entry in {joinup_user_deleted_user_content}.
      $sql = $this->database->select(self::TABLE_USER, 'u')->fields('u', ['uid']);
      $sql->leftJoin(self::TABLE_USER_CONTENT, 'c', 'u.uid = c.uid');
      if ($uids = $sql->isNull('c.uid')->execute()->fetchCol()) {
        $this->database->delete(self::TABLE_USER)->condition('uid', $uids, 'IN')->execute();
      }
    }
  }

  /**
   * Returns a list of relevant entities authored by a given user.
   *
   * @param \Drupal\user\UserInterface $account
   *   The user account.
   *
   * @return array<array-key, array<int|string, int|string>>
   *   Associative array with entity type as keys. Each value is an associative
   *   array. The values of the array are always entity IDs. The keys are
   *   revision IDs if the entity supports revision and entity IDs if not.
   */
  protected function getDeletedUserContent(UserInterface $account): array {
    $content = [];
    foreach (self::ENTITY_TYPES as $entityTypeId => [$ownerKey, $revisionUserKey]) {
      \assert($this->entityTypeManager->getDefinition($entityTypeId)->entityClassImplements(EntityOwnerInterface::class));

      $query = $this->entityTypeManager->getStorage($entityTypeId)->getQuery();
      $or = $query->orConditionGroup()->condition($ownerKey, $account->id());
      if ($revisionUserKey) {
        $query->allRevisions();
        $or->condition($revisionUserKey, $account->id());
      }
      $ids = $query->accessCheck(FALSE)->condition($or)->execute();

      if ($ids) {
        $content[$entityTypeId] = $ids;
      }
    }
    return $content;
  }

  /**
   * Anonymizes content of deleted user accounts.
   *
   * @param \Drupal\user\UserInterface $account
   *   The user account.
   * @param string $entityTypeId
   *   The entity type ID.
   * @param array<array-key, array<int|string, int|string>> $ids
   *   Associative array. The values are always entity IDs. The keys are
   *   revision IDs if the entity supports revision and entity IDs if not.
   */
  protected function setAnonymousAsOwner(UserInterface $account, string $entityTypeId, array $ids): void {
    $storage = $this->entityTypeManager->getStorage($entityTypeId);

    // Revision support.
    if ($isRevisionable = isset(self::ENTITY_TYPES[$entityTypeId][1])) {
      $entities = $storage->loadMultipleRevisions(array_keys($ids));
    }
    else {
      $entities = $storage->loadMultiple(array_unique($ids));
    }

    foreach ($entities as $entity) {
      // Loaded revisions are missing the original.
      $entity->original = clone $entity;

      if ($entity instanceof TranslatableInterface) {
        // Do for each translation.
        foreach ($entity->getTranslationLanguages() as $language) {
          $this->doSetAnonymousAsOwner($entity->getTranslation($language->getId()), $account, $isRevisionable);
        }
      }
      else {
        $this->doSetAnonymousAsOwner($entity, $account, $isRevisionable);
      }
      $entity->save();
    }
  }

  /**
   * Sets anonymous as author.
   *
   * @param \Drupal\user\EntityOwnerInterface $entity
   *   The entity.
   * @param \Drupal\user\UserInterface $account
   *   The user account.
   * @param bool $isRevisionable
   *   Whether this entity is revisionable.
   */
  protected function doSetAnonymousAsOwner(EntityOwnerInterface $entity, UserInterface $account, bool $isRevisionable): void {
    if ($entity->getOwnerId() == $account->id()) {
      $entity->setOwnerId(0);
    }
    if ($isRevisionable) {
      assert($entity instanceof RevisionLogInterface);
      if ($entity->getRevisionUserId() == $account->id()) {
        $entity->setRevisionUserId(0);
      }
    }
    // Prevent notifications.
    $entity->skip_notification = TRUE;
  }

}
