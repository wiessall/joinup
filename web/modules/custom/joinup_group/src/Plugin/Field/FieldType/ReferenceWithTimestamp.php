<?php

declare(strict_types=1);

namespace Drupal\joinup_group\Plugin\Field\FieldType;

use Drupal\Core\Field\Attribute\FieldType;
use Drupal\Core\Field\EntityReferenceFieldItemList;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Defines the 'joinup_group_reference_with_timestamp' field type.
 *
 * This field type is an entity reference field extended with a timestamp
 * property (required), and is intended to be used for withdrawing sharing of
 * group content.
 */
#[FieldType(
  id: "joinup_group_reference_with_timestamp",
  label: new TranslatableMarkup("Entity reference with timestamp"),
  description: new TranslatableMarkup("A field storing an entity reference and a timestamp."),
  category: "reference",
  default_widget: "joinup_group_reference_with_timestamp",
  default_formatter: "joinup_group_reference_with_timestamp",
  list_class: EntityReferenceFieldItemList::class,
  constraints: [
    "ComplexData" => [
      "timestamp" => [
        "Range" => [
          "min" => "-2147483648",
          "max" => "2147483648",
        ],
      ],
    ],
  ],
)]
class ReferenceWithTimestamp extends EntityReferenceItem {

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition): array {
    $schema = parent::schema($field_definition);
    $schema['columns']['timestamp'] = [
      'description' => 'Timestamp belonging to the referenced entity',
      'type' => 'int',
      // Core timestamp also stores signed integer, we're doing the same.
      'unsigned' => FALSE,
    ];
    // Add a standalone index for this column.
    $schema['indexes']['timestamp'] = ['timestamp'];
    return $schema;
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition): array {
    $properties = parent::propertyDefinitions($field_definition);
    $properties['timestamp'] = DataDefinition::create('timestamp')
      ->setLabel(t('Timestamp value'));
    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public static function generateSampleValue(FieldDefinitionInterface $field_definition) {
    $sampleValue = parent::generateSampleValue($field_definition);
    $sampleValue['value'] = \Drupal::time()->getRequestTime() - mt_rand(0, 86400 * 365);
    return $sampleValue;
  }

}
