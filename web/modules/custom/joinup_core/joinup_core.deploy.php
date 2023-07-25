<?php

/**
 * @file
 * Deploy functions for Joinup.
 *
 * This should only contain update functions that rely on the Drupal API and
 * need to run _after_ the configuration is imported.
 *
 * This is applicable in most cases. However, in case the update code enables
 * some functionality that is required for configuration to be successfully
 * imported, it should instead be placed in joinup_core.post_update.php.
 */

declare(strict_types = 1);

/**
 * Import the new version of EIRA.
 */
function joinup_core_deploy_109500(): string {
  $fixtures_helper = \Drupal::getContainer()->get('joinup_rdf.vocabulary_fixtures.helper');
  $fixtures_helper->importFixtures('eira_skos');

  return 'Imported eira_skos and ran after import queries.';
}
