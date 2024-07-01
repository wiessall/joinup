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

use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\Sql\SqlEntityStorageInterface;
use Drupal\joinup_core\DeployAndSmokeTestHelper;
use Drupal\joinup_document\Entity\DocumentInterface;

/**
 * Create Interoperable Europe Act document as custom page.
 */
function joinup_core_deploy_110900(): string {
  return DeployAndSmokeTestHelper::createIeaCustomPage();
}

/**
 * Restores the missing "published" revision of node '701955'.
 */
function joinup_core_deploy_110901(): string {
  $nodeStorage = \Drupal::entityTypeManager()->getStorage('node');
  assert($nodeStorage instanceof SqlEntityStorageInterface);
  $connection = \Drupal::database();
  $node = $nodeStorage->load(701955);
  $previousRevisionIds = $connection->select('node_revision__field_state', 'nr')
    ->fields('nr', ['revision_id', 'field_state_value'])
    ->condition('entity_id', $node->id())
    ->condition('revision_id', $currentRevisionId = $node->getRevisionId(), '<')
    ->orderBy('revision_id')
    ->execute()
    ->fetchAllKeyed();
  $previousPublishedRevisionIds = array_filter(
    $previousRevisionIds,
    fn (string $state): bool => $state === 'published',
  );

  // Skip any action if the problem is already resolved or if the document does
  // not exist anymore.
  if (
    !$node instanceof DocumentInterface ||
    $node->getWorkflowState() !== 'archived' ||
    !empty($previousPublishedRevisionIds)
  ) {
    return "No need for further action";
  }

  // Set the current archived revision to published.
  foreach (['node', 'node_revision'] as $base) {
    $connection->update("{$base}__field_state")
      ->condition('revision_id', $currentRevisionId)
      ->fields(['field_state_value' => 'published'])
      ->execute();
  }
  $revisionLogMessage = $connection->select('node_revision', 't')
    ->fields('t', ['revision_log'])
    ->condition('t.vid', $currentRevisionId)
    ->execute()
    ->fetchField();
  $revisionLogMessage = implode('. ', array_filter([
    $revisionLogMessage,
    'Restored to published programmatically on ' . \Drupal::time()->getCurrentTime() . '.',
  ]));
  $connection->update('node_revision')
    ->condition('vid', $currentRevisionId)
    ->fields(['revision_log' => $revisionLogMessage])
    ->execute();
  $nodeStorage->resetCache([$node->id()]);
  Cache::invalidateTags($node->getCacheTagsToInvalidate());
  return "Orphan archived revision of document node with ID '{$node->id()}' was programmatically changed to published.";
}
