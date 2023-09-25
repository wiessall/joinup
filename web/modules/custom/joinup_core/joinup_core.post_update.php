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

use Drupal\file\Entity\File;
use Drupal\rdf_entity\Entity\Rdf;
use Drupal\rdf_entity\Entity\RdfEntityType;

/**
 * Clean the RDF graph related entities and files.
 */
function joinup_core_post_update_109700(): void {
  $rdf_ids = [
    'http://data.europa.eu/w21/user-graph/e120079b-817b-4c4b-b27b-e97f25cc2c58',
    'http://data.europa.eu/w21/user-graph/ef5c2894-1fa1-43b8-870e-fe55f5e019ae'
  ];
  foreach (Rdf::loadMultiple($rdf_ids) as $rdf) {
    $rdf->delete();
  }
  foreach (File::loadMultiple([525107, 553329]) as $file) {
    $file->delete();
  }
}

/**
 * Manually clean up config files.
 */
function joinup_core_post_update_109701(): void {
  // Since we are trying to clean up the whole entity, and there is the
  // specifics of the RDF entities that require a SPARQL mapping to define the
  // available graphs for the entity, but we do not necessarily want that every
  // subsequent possible config to be dependent on it, we need to manually
  // remove the field because otherwise, the config will request the related
  // entity, and the related entity will require the SPARQL mapping, and this
  // might be already deleted. Note that this might still be a bug.
  $field = \Drupal::entityTypeManager()->getStorage('field_config')->load('rdf_entity.rdf_graph.field_rdf_file');
  $field?->delete();

  $field_storage = \Drupal::entityTypeManager()->getStorage('field_storage_config')->load('rdf_entity.field_rdf_file');
  $field_storage?->delete();
}
