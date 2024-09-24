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

use Drupal\joinup_release\Entity\ReleaseInterface;
use Drupal\pathauto\PathautoState;

/**
 * Fix releases without version string and regenerate their path aliases.
 */
function joinup_core_deploy_111300(): string {
  $entityTypeManager = \Drupal::entityTypeManager();
  $nodeStorage = \Drupal::entityTypeManager()->getStorage('node');
  $pathAliasStorage = $entityTypeManager->getStorage('path_alias');
  $releaseIdsWithoutVersion = $nodeStorage
    ->getQuery()
    ->accessCheck(FALSE)
    ->condition('type', 'release')
    ->notExists('release_number')
    ->execute();
  $changedReleaseCount = 0;

  foreach ($nodeStorage->loadMultiple($releaseIdsWithoutVersion) as $release) {
    assert($release instanceof ReleaseInterface);
    assert($release->get('release_number')->isEmpty());
    // Skip doing anything to releases whose alias does not end with /release -
    // so we don't touch releases having aliases ending with /release-0,
    // /release-1 etc.: these aliases are harmless.
    $pathField = $release->get('path');
    if (!is_string($pathField->alias) || !str_ends_with($pathField->alias, '/release')) {
      continue;
    }
    // Delete the preexisting alias so Redirect module won't create a redirect
    // (which causes the same issue what we're solving here).
    if ($aliasId = $pathField->pid) {
      $pathAliasStorage->load($aliasId)->delete();
    }
    $release->skip_notification = TRUE;
    $release
      ->setSyncing(TRUE)
      ->set('release_number', 'no-version')
      ->set('path', ['pathauto' => PathautoState::CREATE])
      ->save();
    $changedReleaseCount++;
  }

  return sprintf(
    "Added 'no-version' to %s releases which lack version number with an alias ending with '/release'; and re-generated their aliases",
    $changedReleaseCount,
  );
}
