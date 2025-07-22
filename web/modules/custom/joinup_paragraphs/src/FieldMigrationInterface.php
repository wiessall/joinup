<?php

declare(strict_types=1);

namespace Drupal\joinup_paragraphs;

use Drupal\Core\Entity\EntityInterface;

/**
 * Interface for field migration services.
 */
interface FieldMigrationInterface {

  /**
   * Migrates data from one field to another on a given entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to migrate fields on.
   * @param string $from_field
   *   The source field name to migrate data from.
   * @param string $to_field
   *   The target field name to migrate data to.
   *
   * @throws \InvalidArgumentException
   *   If entity does not have the required fields.
   * @throws \Drupal\Core\Entity\EntityStorageException
   *   If there are issues creating paragraphs or saving entities.
   */
  public function migrateField(EntityInterface $entity, string $from_field, string $to_field): void;

}
