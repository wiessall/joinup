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

declare(strict_types=1);

/**
 * Add an /events redirect.
 */
function joinup_core_deploy_200300(): void {
  \Drupal::entityTypeManager()->getStorage('redirect')->create([
    'redirect_source' => ['path' => 'events'],
    'redirect_redirect' => ['uri' => 'internal:/node/726609'],
    'language' => 'und',
    'status_code' => 301,
  ])->save();
}
