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
 * Uninstall Matomo Reporting API module.
 */
function joinup_core_post_update_108000(): void {
  // This cannot be handled as part of config sync because of the sequence of
  // configuration import operations.
  \Drupal::configFactory()
    ->getEditable('matomo_reporting_api.settings')
    ->delete();
}
