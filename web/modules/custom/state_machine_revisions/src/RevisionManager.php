<?php

declare(strict_types=1);

namespace Drupal\state_machine_revisions;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\RevisionableStorageInterface;
use Drupal\Core\TypedData\TranslatableInterface;

/**
 * Manages stuff.
 */
readonly class RevisionManager implements RevisionManagerInterface {

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getLatestRevisionId(ContentEntityInterface $entity): int|string|null {
    $entity_type = $entity->getEntityTypeId();

    if ($storage = $this->entityTypeManager->getStorage($entity_type)) {
      $revision_ids = $storage->getQuery()
        ->accessCheck(FALSE)
        ->allRevisions()
        ->condition($this->entityTypeManager->getDefinition($entity_type)->getKey('id'), $entity->id())
        ->sort($this->entityTypeManager->getDefinition($entity_type)->getKey('revision'), 'DESC')
        ->range(0, 1)
        ->execute();
      if ($revision_ids) {
        return array_keys($revision_ids)[0];
      }
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function isRevisionableEntityType(EntityTypeInterface $entityType): bool {
    return $entityType->isRevisionable();
  }

  /**
   * {@inheritdoc}
   */
  public function isLatestRevision(ContentEntityInterface $entity): bool {
    return $entity->getRevisionId() == $this->getLatestRevisionId($entity);
  }

  /**
   * {@inheritdoc}
   */
  public function loadDefaultRevision(ContentEntityInterface $entity): ?ContentEntityInterface {
    $default_revision = NULL;

    if ($storage = $this->entityTypeManager->getStorage($entity->getEntityTypeId())) {
      $default_revision = $storage->load($entity->id());

      // Ensure we are comparing the same translation as the current entity.
      // @see \Drupal\content_moderation\EntityOperations::isDefaultRevisionPublished()
      if ($default_revision instanceof TranslatableInterface && $default_revision->isTranslatable()) {
        // If there is no translation, then there is no default revision.
        if (!$default_revision->hasTranslation($entity->language()->getId())) {
          return NULL;
        }

        $default_revision = $default_revision->getTranslation($entity->language()->getId());
      }
    }

    return $default_revision;
  }

  /**
   * {@inheritdoc}
   */
  public function loadLatestRevision(ContentEntityInterface $entity): ?ContentEntityInterface {
    if ($latest_revision_id = $this->getLatestRevisionId($entity)) {
      /** @var \Drupal\Core\Entity\RevisionableStorageInterface $storage */
      $storage = $this->entityTypeManager->getStorage($entity->getEntityTypeId());
      assert($storage instanceof RevisionableStorageInterface);
      /** @var \Drupal\Core\Entity\ContentEntityInterface|NULL $revision */
      $revision = $storage->loadRevision($latest_revision_id);
      return $revision;
    }

    return NULL;
  }

}
