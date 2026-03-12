<?php

declare(strict_types=1);

namespace Drupal\eu_oss_catalogue\Plugin\Field\FieldType;

use Drupal\Core\Field\Attribute\FieldType;
use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Defines the field type for validation issues.
 */
#[FieldType(
  id: 'validation_issue',
  label: new TranslatableMarkup('Validation issue'),
  description: new TranslatableMarkup('Stores a single validation issue with key, description, location, and severity.'),
  default_formatter: 'validation_issue',
)]
class ValidationIssueItem extends FieldItemBase {

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition): array {
    return [
      'key' => DataDefinition::create('string')
        ->setLabel(new TranslatableMarkup('Key'))
        ->setDescription(new TranslatableMarkup('The publiccode.yml field key that triggered the issue.')),
      'description' => DataDefinition::create('string')
        ->setLabel(new TranslatableMarkup('Description'))
        ->setDescription(new TranslatableMarkup('Human-readable description of the issue.')),
      'line' => DataDefinition::create('integer')
        ->setLabel(new TranslatableMarkup('Line'))
        ->setDescription(new TranslatableMarkup('Line number where the issue was found.')),
      'column' => DataDefinition::create('integer')
        ->setLabel(new TranslatableMarkup('Column'))
        ->setDescription(new TranslatableMarkup('Column number where the issue was found.')),
      'type' => DataDefinition::create('string')
        ->setLabel(new TranslatableMarkup('Type'))
        ->setDescription(new TranslatableMarkup('The severity type of the issue: error or warning.')),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition): array {
    return [
      'columns' => [
        'key' => [
          'description' => 'The publiccode.yml field key that triggered the issue.',
          'type' => 'varchar',
          'length' => 255,
        ],
        'description' => [
          'description' => 'Human-readable description of the issue.',
          'type' => 'text',
          'size' => 'normal',
        ],
        'line' => [
          'description' => 'Line number where the issue was found.',
          'type' => 'int',
          'unsigned' => TRUE,
        ],
        'column' => [
          'description' => 'Column number where the issue was found.',
          'type' => 'int',
          'unsigned' => TRUE,
        ],
        'type' => [
          'description' => 'The severity type of the issue: error or warning.',
          'type' => 'varchar',
          'length' => 16,
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty(): bool {
    return empty($this->key);
  }

}
