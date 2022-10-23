<?php

/**
 * @file
 * EIF specific procedural functions.
 */

declare(strict_types = 1);

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

  $rdf_meta_entity = $solution->eif_perspective->entity;
  if (empty($rdf_meta_entity)) {
    return;
  }

  $eif_description = \Drupal::entityTypeManager()->getViewBuilder('rdf_meta_entity')->viewField($rdf_meta_entity->field_eif_description, [
    'label' => 'hidden',
  ]);
  $variables['content']['eif_perspective_fields']['field_eif_description'] = $eif_description;
}
