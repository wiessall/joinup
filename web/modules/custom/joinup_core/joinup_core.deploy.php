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

use Drupal\redirect\Entity\Redirect;

/**
 * Remove old "News" page alias in Interoperable Europe collection.
 */
function joinup_core_deploy_0107700(array &$sandbox = []): void {
  $path = 'collection/interoperable-europe/news';
  $ids = \Drupal::entityQuery('redirect')
    ->condition('status_code', '301')
    ->condition('redirect_source__path', $path, '=')
    ->execute();
  $entities = Redirect::loadMultiple($ids);
  \Drupal::entityTypeManager()->getStorage('redirect')->delete($entities);
}
