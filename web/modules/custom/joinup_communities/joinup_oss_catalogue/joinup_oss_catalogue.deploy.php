<?php

/**
 * @file
 * Joinup OSS Catalogue deployment hooks.
 */

declare(strict_types=1);

use Drupal\Component\Utility\Crypt;
use Drupal\Core\Entity\ContentEntityStorageInterface;

/**
 * Queue OSS solutions to be validated.
 */
function joinup_oss_catalogue_deploy_200700(): string {
  $queue = \Drupal::queue('joinup_oss_catalogue_publiccode_validator');
  $queue->createQueue();
  $solutionStorage = \Drupal::service('entity_type.manager')->getStorage('node');
  \assert($solutionStorage instanceof ContentEntityStorageInterface);
  $solutionIds = \Drupal::entityQuery('node')->accessCheck(FALSE)->condition('type', 'oss_solution')->execute();

  array_walk(
    $solutionIds,
    function (string|int $solutionId) use ($queue): void {
      $queue->createItem($solutionId);
    },
  );

  return sprintf(
    "Queued %s OSS solutions to be validated.",
    count($solutionIds)
  );
}

/**
 * Force re-fetch for solutions with invalid release date format.
 */
function joinup_oss_catalogue_deploy_200701(): string {
  $database = \Drupal::database();

  $query = $database->select('node__oss_release_date', 'rd');
  $query->fields('rd', ['entity_id']);
  $query->condition('rd.oss_release_date_value', '%-%', 'NOT LIKE');
  $query->distinct();
  $nids = $query->execute()->fetchCol();

  if (empty($nids)) {
    return 'No OSS solutions with invalid release date format found.';
  }

  $database->update('node__oss_source_hash')
    ->fields(['oss_source_hash_value' => Crypt::hashBase64('Force re-fetch for invalid release date.')])
    ->condition('entity_id', $nids, 'IN')
    ->execute();

  return sprintf(
    'Updated %d OSS solutions with invalid release date format to force re-fetch.',
    count($nids)
  );
}
