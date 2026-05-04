<?php

/**
 * @file
 * Deploy functions for the Joinup core module.
 */

declare(strict_types=1);

/**
 * Trim leading and trailing whitespace from all node titles.
 */
function joinup_core_deploy_201300(): void {
  \Drupal::database()->query('UPDATE {node_field_data} SET title = TRIM(title) WHERE title != TRIM(title)');
  \Drupal::database()->query('UPDATE {node_field_revision} SET title = TRIM(title) WHERE title != TRIM(title)');
}