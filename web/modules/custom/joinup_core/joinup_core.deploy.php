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
 * Set the default value for the "Announcements" field for og_membership.
 */
function joinup_core_deploy_108202(): void {
  $database = \Drupal::database();

  // Only enable the announcements to users that have accepted the terms of use.
  $query = <<<SQL
UPDATE {og_membership} og
SET og.announcements = 1
WHERE og.state = 'active'
AND (
  og.uid IN (SELECT DISTINCT(uid) FROM {entity_legal_document_acceptance})
    OR
  og.uid IN (SELECT entity_id FROM {user__roles} WHERE roles_target_id = 'moderator')
)
SQL;

  $database->query($query)->execute();
}
