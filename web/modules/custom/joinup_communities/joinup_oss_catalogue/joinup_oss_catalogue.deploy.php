<?php

/**
 * @file
 * Deploy functions for Joinup OSS Catalogue.
 */

declare(strict_types=1);

use Drupal\Component\Utility\Crypt;

/**
 * Enforce an update across all solutions.
 */
function joinup_oss_catalogue_deploy_200500(): string {
  \Drupal::database()->update('node__oss_source_hash')
    ->fields(['oss_source_hash_value' => Crypt::hashBase64('Enforce an update across all solutions.')])
    ->execute();

  return 'All values in node__oss_source_hash.oss_source_hash_value were updated.';
}
