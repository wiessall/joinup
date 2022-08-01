<?php

/**
 * @file
 * RDF Entity specific procedural functions.
 */

declare(strict_types = 1);

/**
 * Implements hook_preprocess_rdf_entity().
 */
function ventuno_preprocess_rdf_entity(array &$variables): void {
  // Workaround for incorrect rendering of RDF entity label.
  // @see https://www.drupal.org/project/rdf_entity/issues/3209500
  $variables['label'] = $variables['rdf_entity']->label();
}

/**
 * Implements hook_preprocess_HOOK().
 */
function ventuno_preprocess_rdf_entity__explore_item(array &$variables): void {
  /** @var \Drupal\rdf_entity\RdfInterface $entity */
  $entity = $variables['rdf_entity'];
  // Trim the labels of the items in the Explore block on the homepage down to a
  // manageable size.
  $variables['label'] = _ventuno_smart_trim($entity->label(), 74);
}

/**
 * Implements hook_preprocess_HOOK().
 */
function ventuno_preprocess_rdf_entity__search_result_featured(array &$variables): void {
  _ventuno_preprocess_search_result($variables, 'rdf_entity');
}

/**
 * Implements hook_preprocess_HOOK().
 */
function ventuno_preprocess_rdf_entity__search_result(array &$variables): void {
  _ventuno_preprocess_search_result($variables, 'rdf_entity');
}
