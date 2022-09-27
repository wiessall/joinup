<?php

/**
 * @file
 * Deploy functions for Joinup.
 *
 * This should only contain update functions that rely on the Drupal API and
 * need to run _after_ the configuration is imported.
 *
 * This is applicable in most cases. However in case the update code enables
 * some functionality that is required for configuration to be successfully
 * imported, it should instead be placed in joinup_core.post_update.php.
 */

declare(strict_types = 1);

/**
 * Creates a Link to EIF perspective view from the solution's left-side menu.
 */
function joinup_core_deploy_0108500(): void {
  $eif_perspective_ids = \Drupal::entityQuery('rdf_meta_entity')
    ->condition('type', 'eif_perspective')
    ->execute();
  $eif_perspectives = \Drupal::entityTypeManager()->getStorage('rdf_meta_entity')->loadMultiple($eif_perspective_ids);
  foreach ($eif_perspectives as $eif_perspective) {
    _eif_add_eif_link_to_leftside_menu($eif_perspective);
  }
}
