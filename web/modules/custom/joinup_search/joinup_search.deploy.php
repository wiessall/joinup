<?php

/**
 * @file
 * Deploy functions for Joinup search.
 */

use Drupal\search_api\Entity\Index;

/**
 * Rebuild tracking information.
 */
function joinup_search_deploy_200300(array &$sandbox): string {
  $index = Index::load('published');
  $index->rebuildTracker();

  return 'Tracking information for index publish has been rebuilt';
}
