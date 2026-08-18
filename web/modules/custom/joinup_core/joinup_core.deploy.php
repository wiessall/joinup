<?php

/**
 * @file
 * Deploy hooks for the Joinup Core module.
 */

declare(strict_types=1);

use Drupal\joinup_core\Archiver;

/**
 * Archives solutions having less than 10 members.
 */
function joinup_core_deploy_201600(array &$sandbox): string {
  $archiver = \Drupal::service(Archiver::class);
  assert($archiver instanceof Archiver);
  $archiver->archiveGroupsWithFewMembers($sandbox);

  return sprintf(
    "Archived %s from %s items of %s total.",
    $sandbox['archived'],
    $sandbox['processed'],
    $sandbox['total'],
  );
}

/**
 * Archives content which wasn't updated in the last 3 years.
 */
function joinup_core_deploy_201601(array &$sandbox): string {
  $archiver = \Drupal::service(Archiver::class);
  assert($archiver instanceof Archiver);
  $archiver->archivePotentiallyOutdatedContent($sandbox);

  return sprintf(
    "Archived %s from %s items of %s total.",
    $sandbox['archived'],
    $sandbox['processed'],
    $sandbox['total'],
  );
}

/**
 * Archives solutions which have no releases or distributions.
 */
function joinup_core_deploy_201602(array &$sandbox): string {
  $archiver = \Drupal::service(Archiver::class);
  assert($archiver instanceof Archiver);
  $archiver->archiveSolutionsWithoutReleaseOrDistro($sandbox);

  return sprintf(
    "Archived %s from %s items of %s total.",
    $sandbox['archived'],
    $sandbox['processed'],
    $sandbox['total'],
  );
}

/**
 * Archives groups without group content.
 */
function joinup_core_deploy_201603(array &$sandbox): string {
  $archiver = \Drupal::service(Archiver::class);
  assert($archiver instanceof Archiver);
  $archiver->archiveGroupsWithoutContent($sandbox);

  return sprintf(
    "Archived %s from %s items of %s total.",
    $sandbox['archived'],
    $sandbox['processed'],
    $sandbox['total'],
  );
}
