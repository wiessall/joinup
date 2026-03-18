<?php

/**
 * @file
 * Joinup Core deployment hooks.
 */

declare(strict_types=1);

use Drupal\joinup_core\FileDuplicator;

/**
 * Duplicates files that are the same among entities.
 */
function joinup_core_deploy_201100(): string {
  $fileDuplicator = \Drupal::classResolver()->getInstanceFromDefinition(FileDuplicator::class);
  $messages = $fileDuplicator->processEntities('assessment', 'report', 'documents');
  $messages += $fileDuplicator->processEntities('paragraph', 'binding_requirement', 'requirement_documents');
  return implode("\n", $messages);
}
