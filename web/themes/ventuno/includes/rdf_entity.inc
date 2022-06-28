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
  $variables['label'] = _ventuno_smart_trim($entity->label(), 45);
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

/**
 * Implements hook_preprocess_HOOK() for rdf_entity__solution__group-header.
 */
function ventuno_preprocess_rdf_entity__solution__group_header(array &$variables): void {
  if (isset($variables['content']['releases_overview'])) {
    $variables['content']['releases_overview']['#attributes'] = [
      'class' => [
        'btn',
        'btn-outline-secondary',
        'ms-sm-3',
      ],
    ];
  }
}

/**
 * Implements hook_preprocess_HOOK() for rdf-entity--solution--view-mode-tile.html.twig.
 *
 * - Add a flag that indicates whether to show the download counter.
 */
function ventuno_preprocess_rdf_entity__solution__view_mode_tile(array &$variables): void {
  // Add a flag to indicate if the download counter is visible.
  if (isset($variables['elements']['downloads']['count']['#access'])) {
    $variables['show_download_counter'] = $variables['elements']['downloads']['count']['#access'];
  }

  $rdf_entity = $variables['rdf_entity'];
  if (!$rdf_entity->get('collection')->isEmpty()) {
    /** @var \Drupal\rdf_entity\RdfInterface|null $parent */
    $parent = $rdf_entity->get('collection')->first()->entity;
    $variables['attributes']['data-joinup-parent-id'] = $parent->id();
    $variables['attributes']['data-joinup-parent-label'] = $parent->label();
  }
}
