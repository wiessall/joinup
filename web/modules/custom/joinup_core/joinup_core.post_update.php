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

use Drupal\taxonomy\Entity\Term;

/**
 * Implements hook_post_update_NAME().
 */
function joinup_core_post_update_110200(): string {
  // Most changes took place in ISAICP-7435.
  // @see https://git.fpfis.tech.ec.europa.eu/digit/digit-joinup-dev/-/merge_requests/468
  $mapping = [
    'http://joinup.eu/ontology/topic#authorisation-and-authentication' => 'http://joinup.eu/ontology/topic#eidentity-and-esignature',
    'http://joinup.eu/ontology/topic#culture' => 'http://joinup.eu/ontology/topic#culture-and-education',
    'http://joinup.eu/ontology/topic#education' => 'http://joinup.eu/ontology/topic#culture-and-education',
    'http://joinup.eu/ontology/topic#defence' => 'http://joinup.eu/ontology/topic#ict-security',
    'http://joinup.eu/ontology/topic#economic-analysis' => 'http://joinup.eu/ontology/topic#banking-and-financial-services',
    'http://joinup.eu/ontology/topic#eu-finance' => 'http://joinup.eu/ontology/topic#banking-and-financial-services',
    // The following two were changed in 2 tickets, first in ISAICP-7435 and
    // then in ISAICP-7489.
    'http://joinup.eu/ontology/topic#ejustice' => 'http://joinup.eu/ontology/topic#legal',
    'http://joinup.eu/ontology/topic#law' => 'http://joinup.eu/ontology/topic#legal',
    'http://joinup.eu/ontology/topic#energy' => 'http://joinup.eu/ontology/topic#energy-and-environment',
    'http://joinup.eu/ontology/topic#environment' => 'http://joinup.eu/ontology/topic#energy-and-environment',
    // The following two were changed in 2 tickets, first in ISAICP-7435 and
    // then in ISAICP-7489.
    'http://joinup.eu/ontology/topic#free-and-open-source-software' => 'http://joinup.eu/ontology/topic#open-source-software',
    'http://joinup.eu/ontology/topic#free-and-open-source-software-in-public-administrations' => 'http://joinup.eu/ontology/topic#open-source-software',
    // The following two were changed in 2 tickets, first in ISAICP-7435 and
    // then in ISAICP-7489.
    'http://joinup.eu/ontology/topic#ict-standardisation' => 'http://joinup.eu/ontology/topic#standardisation',
    'http://joinup.eu/ontology/topic#ict-standardisation-in-egovernment' => 'http://joinup.eu/ontology/topic#standardisation',
    'http://joinup.eu/ontology/topic#information' => 'http://joinup.eu/ontology/topic#content-and-knowledge-management',
    // Changed in ISAICP-7939.
    'http://joinup.eu/ontology/topic#innovation' => 'http://joinup.eu/ontology/topic#digital-innovation',
    'http://joinup.eu/ontology/topic#open-government' => 'http://joinup.eu/ontology/topic#egovernment',
    'http://joinup.eu/ontology/topic#politics' => 'http://joinup.eu/ontology/topic#european-policies',
    'http://joinup.eu/ontology/topic#security' => 'http://joinup.eu/ontology/topic#ict-security',
    // There is a typo in the target URI.
    'http://joinup.eu/ontology/topic#statistics' => 'http://joinup.eu/ontology/topic#statistics-and-informatino',
  ];

  $database = \Drupal::database();
  foreach ($mapping as $source => $target) {
    // Ensure the target exists.
    \assert(!empty(Term::load($target)), sprintf('Term %s does not exist.', $target));

    $database->update('user__field_user_professional_domain')
      ->fields(['field_user_professional_domain_target_id' => $target])
      ->condition('field_user_professional_domain_target_id', $source)
      ->execute();
  }

  return 'The professional domains have been updated.';
}
