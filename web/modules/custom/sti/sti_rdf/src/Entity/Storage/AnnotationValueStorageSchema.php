<?php

declare(strict_types=1);

namespace Drupal\sti_rdf\Entity\Storage;

use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\Sql\SqlContentEntityStorageSchema;

/**
 * Adds indexes to annotation_value.
 *
 * The EAV table is queried for two patterns:
 *   - facet computation: (collection_id, field_name) → group by value_key
 *   - filter application: (collection_id, field_name, value_key) → target ids
 *   - reindex: (annotation_id) → delete all
 *
 * Without these indexes, faceting on a collection with non-trivial volume
 * will full-scan the table on every page load.
 */
class AnnotationValueStorageSchema extends SqlContentEntityStorageSchema {

  /**
   * {@inheritdoc}
   */
  protected function getEntitySchema(ContentEntityTypeInterface $entity_type, $reset = FALSE) {
    $schema = parent::getEntitySchema($entity_type, $reset);
    $base_table = $this->storage->getBaseTable();

    $schema[$base_table]['indexes']['av_collection_field'] = ['collection_id', 'field_name'];
    $schema[$base_table]['indexes']['av_collection_field_value'] = ['collection_id', 'field_name', 'value_key'];
    $schema[$base_table]['indexes']['av_annotation'] = ['annotation_id'];
    $schema[$base_table]['indexes']['av_target'] = ['target_entity_type', 'target_entity_id'];

    return $schema;
  }

}
