<?php

/**
 * @file
 * Joinup Core deployment hooks.
 */

declare(strict_types=1);

/**
 * Remove DER updates which were rolled back.
 */
function joinup_core_deploy_201200(): void {
  if (!\Drupal::keyValue('system.schema')->has('dynamic_entity_reference')) {
    return;
  }
  \Drupal::keyValue('system.schema')->set('dynamic_entity_reference', 8000);
}
