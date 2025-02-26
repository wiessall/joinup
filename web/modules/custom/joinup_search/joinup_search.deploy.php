<?php

/**
 * @file
 * Deploy functions for Joinup search.
 */

declare(strict_types=1);

use Drupal\search_api\Entity\Index;

/**
 * Rebuild tracking information.
 */
function joinup_search_deploy_200300(array &$sandbox): string {
  $index = Index::load('published');
  $index->rebuildTracker();

  return 'Tracking information for index publish has been rebuilt';
}
