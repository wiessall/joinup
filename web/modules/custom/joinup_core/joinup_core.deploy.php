<?php

/**
 * @file
 * Joinup Core deployment hooks.
 */

declare(strict_types=1);

use Drupal\joinup_core\FileDuplicator;

/**
 * Remove DER updates which were rolled back.
 */
function joinup_core_deploy_201200(): void {
  if (!\Drupal::keyValue('system.schema')->has('dynamic_entity_reference')) {
    return;
  }
  \Drupal::keyValue('system.schema')->set('dynamic_entity_reference', 8000);
}

/**
 * Duplicates files that are the same among entities.
 */
function joinup_core_deploy_201201(): string {
  $fileDuplicator = \Drupal::classResolver()->getInstanceFromDefinition(FileDuplicator::class);
  $messages = $fileDuplicator->processEntities('assessment', 'report', 'documents');
  $messages += $fileDuplicator->processEntities('paragraph', 'binding_requirement', 'requirement_documents');
  return implode("\n", $messages);
}
