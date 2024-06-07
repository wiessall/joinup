<?php

declare(strict_types=1);

namespace Drupal\eif\Plugin\Field\FieldType;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItem;
use Drupal\Core\Form\FormStateInterface;
use Drupal\text\Plugin\Field\FieldType\TextLongItem;

/**
 * EIF qualified recommendation field type.
 *
 * @FieldType(
 *   id = "eif_qualified_recommendation",
 *   label = @Translation("EIF qualified recommendation"),
 *   description = @Translation("EIF qualified recommendation"),
 *   category = "reference",
 *   default_widget = "eif_qualified_recommendation",
 *   default_formatter = "eif_qualified_recommendation",
 *   list_class = "\Drupal\Core\Field\EntityReferenceFieldItemList",
 * )
 */
class EifQualifiedRecommendation extends EntityReferenceItem {

  /**
   * {@inheritdoc}
   */
  public static function defaultStorageSettings(): array {
    return ['target_type' => 'taxonomy_term'] + parent::defaultStorageSettings();
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultFieldSettings(): array {
    return [
      'handler_settings' => [
        'target_bundles' => ['eif_recommendation' => 'eif_recommendation'],
      ],
      'allowed_formats' => ['basic_html'],
    ] + parent::defaultFieldSettings();
  }

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition): array {
    return parent::propertyDefinitions($field_definition) + TextLongItem::propertyDefinitions($field_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition): array {
    return NestedArray::mergeDeep(parent::schema($field_definition), TextLongItem::schema($field_definition));
  }

  /**
   * {@inheritdoc}
   */
  public function storageSettingsForm(array &$form, FormStateInterface $form_state, $has_data): array {
    $form['target_type']['#access'] = FALSE;
    $form['cardinality_container']['cardinality']['#value'] = FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED;
    $form['#access'] = FALSE;
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function fieldSettingsForm(array $form, FormStateInterface $form_state): array {
    $form = parent::fieldSettingsForm($form, $form_state);
    $form['handler']['#access'] = FALSE;
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty(): bool {
    $value = $this->get('value')->getValue();
    return parent::isEmpty() && ($value === NULL || $value === '');
  }

}
