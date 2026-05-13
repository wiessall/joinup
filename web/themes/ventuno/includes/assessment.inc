<?php

/**
 * @file
 * Assessment specific procedural functions.
 */

declare(strict_types=1);

use Drupal\assessments\AssessmentInterface;
use Drupal\assessments\Entity\AssessmentType;

/**
 * Implements hook_preprocess_assessment__report__view_mode_tile_horizontal().
 */
function ventuno_preprocess_assessment__report__view_mode_tile_horizontal(array &$variables): void {
  _ventuno_preprocess_assessment_tile($variables);
}

/**
 * Implements hook_preprocess_HOOK().
 */
function ventuno_preprocess_assessment__report(array &$variables): void {
  if (isset($variables['content']['entity_print_view_pdf'])) {
    $variables['content']['entity_print_view_pdf']['#attributes']['class'][] = 'btn btn-primary';
  }
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
  if ($publishedAt = $entity->get('published_at')->value) {
    /** @var \Drupal\Core\Datetime\DateFormatterInterface $date_formatter */
    $date_formatter = \Drupal::service('date.formatter');
    $variables['info']['label'] = t('Published: @date', ['@date' => $date_formatter->format($publishedAt, 'date_only')]);
  }

  // Extra data.
  if (isset($variables['content']['provider'])) {
    $variables['extra_data']['submitter'] = $variables['content']['provider'];
  }
}
