<?php

/**
 * @file
 * Joinup OSS Catalogue deployment hooks.
 */

declare(strict_types=1);

use Drupal\Core\Entity\ContentEntityStorageInterface;

/**
 * Queue OSS solutions to be validated.
 */
function joinup_oss_catalogue_deploy_201000(): string {
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
