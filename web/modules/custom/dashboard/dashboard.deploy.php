<?php

/**
 * @file
 * Deploy functions for Dashboard.
 */

declare(strict_types=1);

/**
 * Removes no longer needed states.
 */
function dashboard_deploy_110900(): void {
  $state = \Drupal::state();
  $state->deleteMultiple([
    'joinup_eulogin.accounts_approve',
    'joinup_eulogin.cas_debug',
  ]);
}
