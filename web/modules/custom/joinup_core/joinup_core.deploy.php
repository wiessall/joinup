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

/**
 * Block super-user.
 */
function joinup_core_deploy_0107600(): void {
  /** @var \Drupal\user\UserInterface $account */
  $account = \Drupal::entityTypeManager()->getStorage('user')->load(1);
  /** @var \Drupal\externalauth\AuthmapInterface $authmap */
  $authmap = \Drupal::service('externalauth.authmap');
  $authmap->delete($account->id(), 'cas');
  $account->block()->save();
}
