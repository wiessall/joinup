<?php

/**
 * @file
 * Joinup Release deployment hooks.
 */

declare(strict_types=1);

use Drupal\joinup_core\Update\BackendModeratorRunner;
use Drupal\joinup_release\Update\ArchiveOldReleasesProcess;
/**
 * Archives releases that have not been updated in more than 2 years.
 */
function joinup_release_deploy_201200(array &$sandbox): string {
  $runner = \Drupal::service(BackendModeratorRunner::class);
  $process = \Drupal::classResolver()->getInstanceFromDefinition(
    ArchiveOldReleasesProcess::class
  );

  assert($process instanceof ArchiveOldReleasesProcess);

  $runner->run($process, $sandbox);
  return $sandbox['message'] ?? '';
}
