<?php

/**
 * @file
 * EIF specific procedural functions.
 */

declare(strict_types=1);

/**
 * Implements hook_theme_suggestions_HOOK_alter().
 */
function ventuno_theme_suggestions_eif_wrapper_alter(array &$suggestions, array $variables): void {
  _ventuno_theme_suggestions_alter($suggestions, $variables);
}

/**
 * Implements hook_preprocess_HOOK().
 */
function ventuno_preprocess_rdf_entity__solution__eif_perspective(array &$variables): void {
  /** @var \Drupal\solution\Entity\SolutionInterface $solution */
  $solution = $variables['rdf_entity'];
  if (empty($solution)) {
    return;
  }

  $meta_entity = $solution->eif_perspective->entity;
  if (empty($meta_entity)) {
    return;
  }

  $eif_description = \Drupal::entityTypeManager()->getViewBuilder('meta_entity')->viewField($meta_entity->get('eif_description'));
  $variables['content']['eif_perspective_fields']['eif_description'] = $eif_description;
}

/**
 * Implements hook_preprocess_HOOK().
 */
function ventuno_preprocess_eif_filter_list(array &$variables): void {
  $variables['#attached']['library'][] = 'ventuno/listing-grid';
  $variables['#attached']['library'][] = 'ventuno/eif-filter';
}
