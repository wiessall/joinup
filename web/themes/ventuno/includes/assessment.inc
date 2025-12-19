<?php

/**
 * @file
 * Assessment specific procedural functions.
 */

declare(strict_types=1);

use Drupal\Core\Render\Element;
use Drupal\assessments\AssessmentInterface;
use Drupal\assessments\Entity\AssessmentType;

/**
 * Implements hook_preprocess_assessment__report__view_mode_tile_horizontal().
 */
function ventuno_preprocess_assessment__report__view_mode_tile_horizontal(array &$variables): void {
  _ventuno_preprocess_assessment_tile($variables);
}

/**
 * Prepares some variables for tile view mode.
 */
function _ventuno_preprocess_assessment_tile(array &$variables): void {
  $entity = $variables['elements']['#assessment'];
  \assert($entity instanceof AssessmentInterface);

  // Title / URL.
  $variables['label'] = $entity->label();
  $variables['url'] = !$entity->isNew() ? $entity->toUrl('canonical')->toString() : NULL;

  // Bundle label and icon.
  $bundle_type = $entity->get('type')->entity;
  \assert($bundle_type instanceof AssessmentType);
  $variables['bundle']['label'] = ucfirst($bundle_type->getSingularLabel() ?? $bundle_type->label());
  $variables['bundle']['icon'] = 'file-earmark-check';

  // Info (above the title).
  $variables['info']['icon'] = 'calendar-event';
  if ($changed_time = $entity->getChangedTime()) {
    /** @var \Drupal\Core\Datetime\DateFormatterInterface $date_formatter */
    $date_formatter = \Drupal::service('date.formatter');
    $variables['info']['label'] = t('Published: @date', ['@date' => $date_formatter->format($changed_time, 'date_only')]);
  }

  // Extra data.
  if (isset($variables['content']['provider'])) {
    $variables['extra_data']['submitter'] = $variables['content']['provider'];
  }
}

/**
 * Implements hook_preprocess_paragraph__binding_requirement().
 */
function ventuno_preprocess_paragraph__binding_requirement(array &$variables): void {
  // Only work on the default view mode.
  if ($variables['view_mode'] !== 'default') {
    return;
  }

  $paragraph = $variables['paragraph'];

  $collectChildItems = static function (array $element): array {
    $items = [];
    // We are reusing the element as constructed by the field formatter to
    // maintain cacheability and other render metadata.
    foreach (Element::children($element) as $child_key) {
      $items[] = $element[$child_key];
    }
    return $items;
  };

  $replaceWithSingleChild = static function (array &$element, array $replacement): void {
    foreach (Element::children($element) as $child_key) {
      unset($element[$child_key]);
    }
    $element[0] = $replacement;
  };

  $buildItemList = static function (string $listType, array $items): array {
    return [
      '#theme' => 'item_list',
      '#list_type' => $listType,
      '#items' => $items,
    ];
  };

  // Expression items are displayed as an ordered list, only labels.
  $expressionItems = $collectChildItems($variables['content']['expression']);
  $expressionList = $buildItemList('ol', $expressionItems);
  $replaceWithSingleChild($variables['content']['expression'], $expressionList);

  // Affected services are displayed as an unordered list, with thematic areas
  // listed after as a combined string of labels.
  $serviceEntities = $paragraph->get('affects')->referencedEntities();
  $thematicLabelsById = [];
  foreach ($serviceEntities as $serviceEntity) {
    foreach ($serviceEntity->get('thematic_area')->referencedEntities() as $thematicEntity) {
      $thematicLabelsById[$thematicEntity->id()] = $thematicEntity->label();
    }
  }

  $thematicSuffix = !empty($thematicLabelsById)
    ? t('<p class="thematic-areas">Functions: @labels</p>', ['@labels' => implode(', ', $thematicLabelsById)])
    : '';

  $affectsItems = $collectChildItems($variables['content']['affects']);
  $affectsList = $buildItemList('ol', $affectsItems);
  $replaceWithSingleChild($variables['content']['affects'], $affectsList);
  $variables['content']['affects'][1] = [
    '#type' => 'markup',
    '#markup' => $thematicSuffix,
  ];

  // Affected stakeholders are displayed as a combined string of the
  // participant type labels.
  $participationEntities = $paragraph->get('has_participation')->referencedEntities();
  $participantTypeLabels = [];
  foreach ($participationEntities as $participationEntity) {
    foreach ($participationEntity->get('participant_type')->referencedEntities() as $typeEntity) {
      $participantTypeLabels[$typeEntity->id()] = $typeEntity->label();
    }
  }

  $replaceWithSingleChild($variables['content']['has_participation'], [
    '#theme' => 'markup',
    '#markup' => !empty($participantTypeLabels)
      ? '<p class="participant-types">' . implode(', ', $participantTypeLabels) . '</p>'
      : '',
  ]);

  // Assets are displayed as an unordered list.
  $assetItems = $collectChildItems($variables['content']['asset']);
  $assetList = $buildItemList('ul', $assetItems);
  $replaceWithSingleChild($variables['content']['asset'], $assetList);

  // Requirement documents are displayed as an unordered list.
  $requirementDocumentItems = $collectChildItems($variables['content']['requirement_documents']);
  $requirementDocumentsList = $buildItemList('ul', $requirementDocumentItems);
  $replaceWithSingleChild($variables['content']['requirement_documents'], $requirementDocumentsList);
}
