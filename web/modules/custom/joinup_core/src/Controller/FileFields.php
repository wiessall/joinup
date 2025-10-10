<?php

declare(strict_types=1);

namespace Drupal\joinup_core\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityFieldManagerInterface;

/**
 * List file field instances.
 */
class FileFields extends ControllerBase {

  public function __construct(
    protected readonly EntityFieldManagerInterface $entityFieldManager,
  ) {}

  /**
   * Generates the list.
   */
  public function listing(): array {
    $fields_structure = [];
    $fields_structure = array_merge_recursive($fields_structure, $this->entityFieldManager->getFieldMapByFieldType('file'));
    $fields_structure = array_merge_recursive($fields_structure, $this->entityFieldManager->getFieldMapByFieldType('image'));

    $build['table'] = [
      '#type' => 'table',
      '#header' => $this->buildHeader(),
      '#title' => $this->t('File fields'),
      '#rows' => [],
      '#empty' => $this->t('There are no file fields yet.'),
    ];
    foreach ($fields_structure as $entity_type => $fields) {
      foreach ($fields as $field_name => $field) {
        foreach ($field['bundles'] as $bundle) {
          $build['table']['#rows']["$entity_type$bundle$field_name"] = $this->buildRow($entity_type, $bundle, $field_name);
        }
      }
    }
    sort($build['table']['#rows']);
    return $build;
  }

  /**
   * Builds one row of the file field definition table.
   *
   * @param string $entity_type
   *   The entity type.
   * @param string $bundle
   *   The bundle.
   * @param string $field_name
   *   The field name.
   *
   * @return array
   *   The render array of one row.
   */
  protected function buildRow($entity_type, $bundle, $field_name): array {
    $info = $this->entityFieldManager->getFieldDefinitions($entity_type, $bundle);
    /** @var \Drupal\Core\Field\FieldDefinitionInterface $field_info */
    $field_info = $info[$field_name];
    $dir = $field_info->getSetting('file_directory');
    $extensions = $field_info->getSetting('file_extensions');

    // BaseFieldDefinition doesn't have a label() method,
    // use getLabel() instead.
    $label = method_exists($field_info, 'label') ? $field_info->label() : $field_info->getLabel();

    return [
      'entity_type' => $entity_type,
      'bundle' => $bundle,
      'field_name' => $label . " ($field_name)",
      'storage_path' => $dir,
      'allowed_extensions' => $extensions,
    ];
  }

  /**
   * Builds the table header.
   *
   * @return array
   *   Render array of table header.
   */
  protected function buildHeader(): array {
    $header = [
      'entity_type' => [
        'data' => $this->t('Entity type'),
        'field' => 'entity_type',
      ],
      'bundle' => [
        'data' => $this->t('Bundle'),
        'field' => 'bundle',
      ],
      'field_name' => [
        'data' => $this->t('Field name'),
        'field' => 'field_name',
      ],
      'storage_path' => [
        'data' => $this->t('Storage directory'),
        'field' => 'field_name',
      ],
      'allowed_extensions' => [
        'data' => $this->t('Allowed extensions'),
        'field' => 'allowed_extensions',
      ],
    ];
    return $header;
  }

}
