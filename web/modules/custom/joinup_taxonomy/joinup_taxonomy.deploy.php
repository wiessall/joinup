<?php

/**
 * @file
 * Install, update, uninstall hook implementations for Joinup Taxonomy module.
 */

declare(strict_types=1);

/**
 * Add back EU to the list of countries.
 */
function joinup_taxonomy_deploy_200800(): void {
  \Drupal::entityTypeManager()->getStorage('taxonomy_term')->create([
    'vid' => 'spatial',
    'tid' => 10120,
    'uuid' => '0e15426a-db00-4869-9ef1-7dac0caf1aeb',
    'uri' => 'http://publications.europa.eu/resource/authority/country/EUR',
    'name' => 'European Union',
    'code' => 'EU',
    'eu_member' => FALSE,
  ])->save();
}
