<?php

/**
 * @file
 * Contains View related hooks for EU OSS Catalogue module.
 */

declare(strict_types=1);

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Field\FieldTypePluginManagerInterface;
use Drupal\Core\StringTranslation\PluralTranslatableMarkup;
use Drupal\eu_oss_catalogue\Plugin\Field\RelevantField;

/**
 * Implements hook_views_data_alter().
 */
function eu_oss_catalogue_views_data_alter(array &$data): void {
  $entityTypeManager = Drupal::entityTypeManager();

  foreach ($entityTypeManager->getDefinitions() as $entityInfo) {
    if ($baseTable = $entityInfo->getBaseTable()) {
      $data[$baseTable]['translation_list'] = [
        'title' => 'Translation list',
        'help' => t('Displays all available translations.'),
        'field' => [
          'id' => 'translation_list',
        ],
      ];
    }
  }
}

/**
 * Implements hook_views_data().
 */
function eu_oss_catalogue_views_data(): array {
  $viewsData = [];

  $entityFieldManager = \Drupal::service('entity_field.manager');
  \assert($entityFieldManager instanceof EntityFieldManagerInterface);
  $fieldTypeManager = \Drupal::service('plugin.manager.field.field_type');
  \assert($fieldTypeManager instanceof FieldTypePluginManagerInterface);

  foreach ($entityFieldManager->getFieldDefinitions('node', 'oss_solution') as $fieldName => $fieldDefinition) {
    if (!is_a($fieldDefinition->getClass(), RelevantField::class, TRUE)) {
      continue;
    }

    $sourceFields = $fieldDefinition->getSetting(RelevantField::SOURCE_FIELDS_SETTING_KEY);
    $label = new PluralTranslatableMarkup(
      count($sourceFields),
      'Value of the <code>@field-name</code> field from the relevant translation',
      'Value with fallback from the relevant translation using these fields, in the following order: <code>@field-names</code>',
      [
        '@field-names' => implode(', ', $sourceFields),
        '@field-name' => implode(', ', $sourceFields),
      ]
    );

    $viewsData['node_field_data'][$fieldName] = [
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

  return $viewsData;
}
