<?php

/**
 * @file
 * Contains View related hooks for EU OSS Catalogue module.
 */

declare(strict_types=1);

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\Sql\SqlContentEntityStorageException;
use Drupal\Core\Entity\Sql\SqlEntityStorageInterface;
use Drupal\Core\Entity\Sql\TableMappingInterface;
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

  $translationFilter = _eu_oss_catalogue_translation_filter();
  if ($translationFilter) {
    $viewsData = NestedArray::mergeDeep($viewsData, $translationFilter);
  }

  return $viewsData;
}

/**
 * Returns the Translation filter Views definition.
 *
 * @return array|null
 *   Translation filter Views definition for node views, if node has the
 *   expected schema.
 */
function _eu_oss_catalogue_translation_filter(): ?array {
  // Only if the node definition has both default_langcode and langcode fields,
  // and they're stored in the same table.
  $entityType = \Drupal::entityTypeManager()->getDefinition('node');
  $storage = \Drupal::entityTypeManager()->getStorage('node');
  $tableMapping = $storage instanceof SqlEntityStorageInterface
    ? $storage->getTableMapping()
    : NULL;
  if (!$tableMapping instanceof TableMappingInterface) {
    return NULL;
  }

  $defaultLangcodeKey = $entityType->getKey('default_langcode');
  $langcodeKey = $entityType->getKey('langcode');
  if (!$defaultLangcodeKey || !$langcodeKey) {
    return NULL;
  }
  try {
    $defaultLangcodeTable = $tableMapping->getFieldTableName($defaultLangcodeKey);
    $langcodeTable = $tableMapping->getFieldTableName($langcodeKey);
  }
  catch (SqlContentEntityStorageException) {
    return NULL;
  }
  if (!$defaultLangcodeTable || $langcodeTable !== $defaultLangcodeTable) {
    return NULL;
  }

  return [
    $langcodeTable => [
      'translated_to' => [
        'title' => t('Has translation in'),
        'help' => t('Displays only the results having translation in the selected language.'),
        'real field' => $langcodeKey,
        'filter' => ['id' => 'eu_oss_catalogue_translation_filter'],
      ],
    ],
  ];
}
