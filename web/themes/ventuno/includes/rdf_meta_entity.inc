<?php

/**
 * @file
 * RDF Meta Entity specific procedural functions.
 */

declare(strict_types=1);

use Drupal\Core\Url;

/**
 * Implements hook_preprocess_HOOK().
 */
function ventuno_preprocess_rdf_meta_entity(array &$variables): void {
  /** @var \Drupal\rdf_meta_entity\Entity\RdfMetaEntityInterface $rdf_meta_entity */
  $rdf_meta_entity = $variables['rdf_meta_entity'];
  /** @var \Drupal\solution\Entity\SolutionInterface $solution */
  $solution = $variables['rdf_meta_entity']->getTargetEntity();
  $variables['solution_label'] = $solution->label();
  $variables['solution_url'] = $solution->toUrl()->setAbsolute()->toString();
  $route_parameters = ['rdf_meta_entity' => $rdf_meta_entity->id()];
  $url_options = [
    'query' => [
      'destination' => Url::fromRoute('entity.rdf_entity.eif_perspective', [
        'rdf_entity' => $solution->id(),
      ])->getInternalPath(),
    ],
  ];
  $edit_url = Url::fromRoute('entity.rdf_meta_entity.edit_form', $route_parameters, $url_options);
  if ($edit_url->access()) {
    $variables['edit_url'] = $edit_url->setAbsolute()->toString();
  }
}
