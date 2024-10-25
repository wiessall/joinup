<?php

declare(strict_types=1);

namespace Drupal\joinup_log;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\Sql\SqlContentEntityStorage;

/**
 * Defines the storage handler class for Portal log entity.
 */
class JoinupLogStorage extends SqlContentEntityStorage {

  /**
   * {@inheritdoc}
   */
  public function save(EntityInterface $entity): void {
    if (!$entity->isNew()) {
      throw new EntityStorageException('Portal log entities are read only entities. Saving them once created is not allowed.');
    }
    parent::save($entity);
  }

}
