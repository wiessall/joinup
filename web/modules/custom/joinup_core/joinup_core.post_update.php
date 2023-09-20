<?php

/**
 * @file
 * Post update functions for Joinup.
 *
 * This should only contain update functions that rely on the Drupal API but
 * need to run _before_ the configuration is imported.
 *
 * For example this can be used to enable a new module that needs to have its
 * code available for the configuration to be successfully imported or updated.
 *
 * In most cases though update code should be placed in joinup_core.deploy.php.
 */

declare(strict_types = 1);

/**
 * Clean tallinn data.
 */
function joinup_core_post_update_109700(): void {
  $storage = \Drupal::entityTypeManager()->getStorage('node');
  $entities = $storage->loadByProperties([
    'type' => 'tallinn_report',
  ]);
  $storage->delete($entities);

  $collection = \Drupal::entityTypeManager()
    ->getStorage('rdf_entity')
    ->load('http://data.europa.eu/w21/5f4c0dae-f521-4d00-a0cf-e1dce0a128a3');
  $collection->delete();
}
