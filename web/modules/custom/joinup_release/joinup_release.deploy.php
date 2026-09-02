<?php

/**
 * @file
 * Post update functions for Joinup Release module.
 */

declare(strict_types=1);

use Drupal\joinup_release\Update\SolutionRevisionRecovery;

/**
 * Recover lost solution revisions.
 */
function joinup_release_deploy_201600(array &$sandbox): string {
  return \Drupal::classResolver(SolutionRevisionRecovery::class)->run($sandbox);
}
