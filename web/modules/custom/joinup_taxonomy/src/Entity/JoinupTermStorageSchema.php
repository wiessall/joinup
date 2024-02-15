<?php

declare(strict_types=1);

namespace Drupal\joinup_taxonomy\Entity;

use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\taxonomy\TermStorageSchema;

/**
 * Adds unique keys index to the 'uri' field.
 */
class JoinupTermStorageSchema extends TermStorageSchema {

  /**
   * {@inheritdoc}
   */
  public function getDedicatedTableSchema(FieldStorageDefinitionInterface $storage_definition, ?ContentEntityTypeInterface $entity_type = NULL): array {
    $schema = parent::getDedicatedTableSchema($storage_definition, $entity_type);

    // Add a unique index on 'uri' column to increase queries performance.
    if ($storage_definition->getName() === 'uri') {
      $tableMapping = $this->storage->getTableMapping();
      $tableName = $tableMapping->getDedicatedDataTableName($storage_definition);
      $schema[$tableName]['unique keys']['uri'] = ['uri'];
    }

    return $schema;
  }

}
