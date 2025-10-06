<?php

declare(strict_types=1);

namespace Drupal\assessments\Plugin\Field\FieldType;

use Drupal\Component\Utility\Random;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Defines the 'assessment_provider' field type.
 *
 * @FieldType(
 *   id = "assessment_provider",
 *   label = @Translation("Assessment provider"),
 *   description = @Translation("Union entity or public sector body which is providing the report."),
 *   default_widget = "assessment_provider",
 *   default_formatter = "assessment_provider",
 * )
 */
final class AssessmentProviderItem extends FieldItemBase {

  /**
   * {@inheritdoc}
   */
  public function isEmpty(): bool {
    return match ($this->get('value')->getValue()) {
      NULL, '' => TRUE,
      default => FALSE,
    };
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition): array {
    $properties['type'] = DataDefinition::create('string')
      ->setLabel(t('Provider type'))
      ->setRequired(TRUE);

    $properties['country'] = DataDefinition::create('string')
      ->setLabel(t('Country code'))
      ->setRequired(FALSE);

    $properties['value'] = DataDefinition::create('string')
      ->setLabel(t('Text value'))
      ->setRequired(TRUE);

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition): array {

    $columns = [
      'type' => [
        'type' => 'varchar',
        'length' => 255,
        'not null' => TRUE,
        'default' => '',
      ],
      'country' => [
        'type' => 'varchar',
        'length' => 2,
        'not null' => FALSE,
      ],
      'value' => [
        'type' => 'varchar',
        'length' => 255,
        'not null' => TRUE,
        'default' => '',
      ],
    ];

    return [
      'columns' => $columns,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function generateSampleValue(FieldDefinitionInterface $field_definition): array {
    $random = new Random();
    $values['value'] = $random->word(mt_rand(1, 50));
    return $values;
  }

}
