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

use Drupal\search_api\Entity\Index;
use Drupal\search_api\Utility\Utility;

/**
 * Mark all releases as needing reindexing.
 */
function joinup_core_deploy_200300(array &$sandbox): string {
  if (empty($sandbox['current'])) {
    $sandbox['current'] = 0;
    $query = \Drupal::entityQuery('node')
      ->accessCheck(FALSE)
      ->condition('type', 'release')
      ->condition('status', 1);
    $sandbox['nids'] = $query->execute();
    if (empty($sandbox['nids'])) {
      return 'No releases found.';
    }
    $sandbox['total'] = count($sandbox['nids']);
  }
  $index_id = 'published';
  $datasource_id = 'entity:node';
  $index = Index::load($index_id);

  $nids = array_splice($sandbox['nids'], 0, 50);
  $document_ids = [];
  foreach ($nids as $nid) {
    $document_ids[] = Utility::createCombinedId($datasource_id, $nid . ':' . \Drupal::languageManager()->getCurrentLanguage()->getId());
  }

  $documents = $index->loadItemsMultiple($document_ids);
  $index->indexSpecificItems($documents);

  $sandbox['current'] += count($nids);
  $sandbox['#finished'] = $sandbox['current'] / $sandbox['total'];
  return sprintf('Reindexed %d of %d releases.', $sandbox['current'], $sandbox['total']);
}
