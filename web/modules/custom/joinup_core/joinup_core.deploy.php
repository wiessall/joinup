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
 * Impersonate the passwords of users not linked to EU Login.
 */
function joinup_core_deploy_108202(?array &$sandbox = NULL): void {
  // While using CAS Account Link module, only the paired accounts passwords
  // were impersonated. The other passwords were kept to allow un-paired account
  // users to link their accounts through their Drupal credentials. As the
  // pairing mechanism is being dismantled, we impersonate all other passwords.
  // Not using entity API for performance reasons.
  \Drupal::database()
    ->query("UPDATE {users_field_data}
      SET pass = CONCAT(
        '\$S\$E',
        SUBSTR(
          SHA2(
            CONCAT(name, uid, mail, timezone, UUID()),
            256
          ),
          1,
          51
        )
      )
      WHERE uid NOT IN(SELECT uid FROM {authmap})");
}
