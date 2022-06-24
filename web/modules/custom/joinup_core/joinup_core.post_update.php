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
 * Import the EIF solution type vocabulary.
 */
function joinup_core_post_update_108200(?array &$sandbox = NULL): void {
  $fixtures_helper = \Drupal::getContainer()->get('joinup_rdf.vocabulary_fixtures.helper');
  $fixtures_helper->importFixtures('eif_solution_type');
}
