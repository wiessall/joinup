<?php

/**
 * @file
 * Meta Entity specific procedural functions.
 */

declare(strict_types=1);

use Drupal\Core\Url;

/**
 * Implements hook_theme_suggestions_HOOK_alter().
 */
function ventuno_theme_suggestions_meta_entity_alter(array &$suggestions, array $variables): void {
  _ventuno_theme_suggestions_alter($suggestions, $variables);
}

/**
 * Implements hook_preprocess_HOOK().
 */
function ventuno_preprocess_meta_entity(array &$variables): void {
  /** @var \Drupal\meta_entity\Entity\MetaEntityInterface $meta_entity */
  $meta_entity = $variables['meta_entity'];
  if ($meta_entity->bundle() !== 'eif_perspective') {
    return;
  }

  /** @var \Drupal\joinup_solution\Entity\SolutionInterface $solution */
  $solution = $meta_entity->getTargetEntity();
  $variables['solution_label'] = $solution->label();
  $variables['solution_url'] = $solution->toUrl()->setAbsolute()->toString();
  $route_parameters = ['meta_entity' => $meta_entity->id()];
  $url_options = [
    'query' => [
      'destination' => Url::fromRoute('entity.rdf_entity.eif_perspective', [
        'rdf_entity' => $solution->id(),
      ])->getInternalPath(),
    ],
  ];
  $edit_url = Url::fromRoute('entity.meta_entity.edit_form', $route_parameters, $url_options);
  if ($edit_url->access()) {
    $variables['edit_url'] = $edit_url->setAbsolute()->toString();
  }
}
