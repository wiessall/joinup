<?php

/**
 * @file
 * Contains View related hooks for EU OSS Catalogue module.
 */

declare(strict_types = 1);

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Field\FieldTypePluginManagerInterface;
use Drupal\eu_oss_catalogue\Plugin\Field\RichestField;
use Drupal\eu_oss_catalogue\Plugin\Field\RichestFieldWithFallback;

/**
 * Implements hook_views_data_alter().
 */
function eu_oss_catalogue_views_data_alter(array &$data): void {
  $entityTypeManager = Drupal::entityTypeManager();

  foreach ($entityTypeManager->getDefinitions() as $entity_info) {
    $data[$entity_info->getBaseTable()]['translation_list'] = [
      'title' => 'Translation list',
      'help' => t('Displays all available translations.'),
      'field' => [
        'id' => 'translation_list',
      ],
    ];
  }
}

/**
 * Implements hook_views_data().
 */
function eu_oss_catalogue_views_data() {
  $views_data = [];
  $entityFieldManager = \Drupal::service('entity_field.manager');
  \assert($entityFieldManager instanceof EntityFieldManagerInterface);
  $fieldTypeManager = \Drupal::service('plugin.manager.field.field_type');
  \assert($fieldTypeManager instanceof FieldTypePluginManagerInterface);
  foreach ($entityFieldManager->getFieldDefinitions('node', 'oss_solution') as $fieldName => $fieldDefinition) {
    if (!is_a($fieldDefinition->getClass(), RichestField::class, TRUE)) {
      continue;
    }

    $label = is_a($fieldDefinition->getClass(), RichestFieldWithFallback::class, TRUE)
      ? t('Value with fallback from the relevant translation using these fields, in this order: <code>@field-names</code>', [
        '@field-names' => implode(', ', $fieldDefinition->getSetting('computed_sources')),
      ])
      : t('Value of the <code>@field-name</code> field from the relevant translation', [
        '@field-name' => $fieldDefinition->getSetting('computed_source'),
      ]);

    $views_data['node_field_data'][$fieldName] = [
      'title' => $fieldDefinition->getLabel(),
      'group' => t('EU OSS Catalogue'),
      'help' => $label,
      'field' => [
        'id' => 'field',
        'field_name' => $fieldName,
        'default_formatter' => $fieldTypeManager->getDefinition($fieldDefinition->getType())['default_formatter'],
        'click sortable' => FALSE,
      ],
    ];
  }
  return $views_data;
}
