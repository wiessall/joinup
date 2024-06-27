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

use Drupal\Component\Utility\Variable;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\Sql\SqlEntityStorageInterface;
use Drupal\Core\Session\AccountSwitcherInterface;
use Drupal\Core\Utility\UpdateException;
use Drupal\joinup_core\DeployAndSmokeTestHelper;
use Drupal\joinup_document\Entity\DocumentInterface;
use Drupal\joinup_workflow\WorkflowHelperInterface;
use Drupal\solution\Entity\Solution;
use Drupal\user\Entity\User;
use Drupal\user\UserInterface;

// Temporary constant.
// phpcs:ignore DrupalPractice.Constants.GlobalConstant.GlobalConstant
const SOLUTION_ARCHIVAL_ACTOR_ID = 744195;

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

/**
 * Archives solutions specified by Functional Team.
 */
function joinup_core_deploy_110902(&$sandbox): string {
  if (!isset($sandbox['remaining'])) {
    $stream = fopen(__DIR__ . '/solutions-to-archive.csv', 'r');
    // CSV is small enough to store it in memory.
    while (is_array($item = fgetcsv($stream))) {
      $sandbox['remaining'][$item[0]] = $item[1] ?? $item[0];
    }
    // Drop header.
    unset($sandbox['remaining']['ID']);
    fclose($stream);
    $sandbox['total'] = count($sandbox['remaining']);
    $sandbox['processed'] = 0;
  }

  $accountSwitcher = \Drupal::service('account_switcher');
  assert($accountSwitcher instanceof AccountSwitcherInterface);
  $storage = \Drupal::entityTypeManager()->getStorage('rdf_entity');
  $storage->resetCache();
  $logger = Drupal::logger('joinup_core');
  $workflowHelper = \Drupal::service('joinup_workflow.workflow_helper');
  assert($workflowHelper instanceof WorkflowHelperInterface);

  // We need a moderator account to perform the workflow state change.
  if (!($actor = User::load(SOLUTION_ARCHIVAL_ACTOR_ID)) instanceof UserInterface) {
    throw new UpdateException(sprintf(
      "Cannot find the predefined actor for the operation. Was looking for user account with id '%s'.",
      SOLUTION_ARCHIVAL_ACTOR_ID
    ));
  }
  if (!$actor->hasRole('moderator')) {
    throw new UpdateException('The predefined actor does not have the moderator role.');
  }

  $items = array_splice($sandbox['remaining'], 0, 50);
  $solutions = $storage->loadMultiple(array_keys($items));
  $notLoadableSolutions = array_diff_key($items, $solutions);
  $accountSwitcher->switchTo($actor);

  foreach ($solutions as $id => $solution) {
    if (!$solution instanceof Solution) {
      $logger->warning(sprintf(
        "The RDF entity with ID '%s' is not a solution (referred with title '%s' in the spreadsheet)",
        $id,
        $items[$id],
      ));
      continue;
    }

    if ($solution->isArchived() || !$solution->isTargetWorkflowStateAllowed('archived')) {
      $logger->warning(sprintf(
        "The solution with ID '%s' (referred with title '%s' in the spreadsheet) cannot be archived. Its current state is '%s'.",
        $id,
        $items[$id],
        $solution->getWorkflowState(),
      ));
      continue;
    }

    try {
      $solution->skip_notification = TRUE;
      $solution->notifyContactOnly = TRUE;
      $workflowHelper->archiveEntity($solution);
    }
    catch (\Exception $exception) {
      $logger->error(sprintf(
        "The solution with ID '%s' (referred with title '%s' in the spreadsheet) cannot be archived:\n%s",
        $id,
        $items[$id],
        $exception->getMessage(),
      ));
    }
  }

  if (!empty($notLoadableSolutions)) {
    $logger->warning(sprintf(
      "The following solutions were not found:\n%s",
      Variable::export($notLoadableSolutions),
    ));
  }

  $accountSwitcher->switchBack();

  $sandbox['processed'] += count($items);
  $sandbox['#finished'] = (int) empty($sandbox['remaining']);
  return sprintf(
    "Processed %s solutions from total %s items to archive.",
    $sandbox['processed'],
    $sandbox['total'],
  );
}
