<?php

declare(strict_types=1);

namespace Drupal\sti_rdf\Entity;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;

/**
 * Defines the Annotation Value entity.
 *
 * One row per (annotation, field_name, value) tuple. Holds the denormalised
 * EAV view of annotation node fields so the collection search controller can
 * facet on heterogeneous schemas without per-collection Search API config.
 *
 * This entity is internal — no UI, no canonical URL, no list builder. It is
 * a storage detail.
 *
 * @ContentEntityType(
 *   id = "annotation_value",
 *   label = @Translation("Annotation Value"),
 *   handlers = {
 *     "storage" = "Drupal\Core\Entity\Sql\SqlContentEntityStorage",
 *     "storage_schema" = "Drupal\sti_rdf\Entity\Storage\AnnotationValueStorageSchema",
 *     "views_data" = "Drupal\views\EntityViewsData",
 *   },
 *   base_table = "annotation_value",
 *   admin_permission = "administer site configuration",
 *   entity_keys = {
 *     "id" = "id",
 *     "uuid" = "uuid",
 *   },
 *   internal = TRUE,
 * )
 */
final class AnnotationValue extends ContentEntityBase {

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['annotation_id'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Annotation node ID'))
      ->setDescription(t('The annotation node these EAV rows were derived from.'))
      ->setRequired(TRUE)
      ->setSetting('unsigned', TRUE);

    $fields['collection_id'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Collection ID'))
      ->setDescription(t('Logical collection this annotation belongs to (e.g. "dah", "fao-agob").'))
      ->setSetting('max_length', 128)
      ->setRequired(TRUE);

    $fields['target_entity_type'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Target entity type'))
      ->setDescription(t('Entity type of the thing the annotation describes (typically "node").'))
      ->setSetting('max_length', 64)
      ->setRequired(TRUE);

    $fields['target_entity_id'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Target entity ID'))
      ->setDescription(t('ID of the thing the annotation describes (e.g. the solution).'))
      ->setRequired(TRUE)
      ->setSetting('unsigned', TRUE);

    $fields['field_name'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Field name'))
      ->setDescription(t('Machine name of the annotation field this row records.'))
      ->setSetting('max_length', 128)
      ->setRequired(TRUE);

    // `value_key` is what we filter/group by: scalar for primitives, target_id
    // string for entity refs. `value_label` is the human-readable label for
    // display in the facet UI.
    $fields['value_key'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Value key'))
      ->setDescription(t('Canonical value used for filtering and grouping.'))
      ->setSetting('max_length', 512)
      ->setRequired(TRUE);

    $fields['value_label'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Value label'))
      ->setDescription(t('Display label for the value (entity label for refs, otherwise == value_key).'))
      ->setSetting('max_length', 512);

    return $fields;
  }

}
