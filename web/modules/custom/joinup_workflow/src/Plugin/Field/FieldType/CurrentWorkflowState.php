<?php

declare(strict_types=1);

namespace Drupal\joinup_workflow\Plugin\Field\FieldType;

use Drupal\Component\Utility\Random;
use Drupal\Core\Field\Attribute\FieldType;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Computed field that shows the current workflow state.
 */
#[FieldType(
  id: "current_workflow_state",
  label: new TranslatableMarkup("Current workflow state"),
  description: new TranslatableMarkup("Computed field that shows the current workflow state."),
  default_widget: "current_workflow_state_widget",
  default_formatter: "current_workflow_state_field_formatter",
  no_ui: TRUE,
  list_class: CurrentWorkflowStateFieldItemList::class,
)]
class CurrentWorkflowState extends FieldItemBase {

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties = [];
    $properties['value'] = DataDefinition::create('string')->setLabel(new TranslatableMarkup('Current state'));

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public static function generateSampleValue(FieldDefinitionInterface $field_definition) {
    $random = new Random();
    return ['value' => $random->word(mt_rand(4, 16))];
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    return empty($this->get('value')->getValue());
  }

}
