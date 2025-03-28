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

use Drupal\rdf_sync\Model\SyncMethod;

/**
 * Delete rdf sync for topic vocabulary.
 */
function joinup_core_deploy_200308(array &$sandbox): string {
  $storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
  $query = \Drupal::database()->select('rdf_sync_uri', 'r')
    ->fields('r', ['entity_id'])
    ->condition('bundle', 'topic');

  $result = $query->execute();
  $ids = $result->fetchCol();

  if (!isset($sandbox['total'])) {
    $sandbox['total'] = count($ids);
    $sandbox['current'] = 0;
  }

  if (!empty($ids)) {
    foreach ($ids as $id) {
      if ($entity = $storage->load($id)) {
        if ($entity->hasField('uri') && $entity->get('uri')->value) {
          \Drupal::getContainer()->get('rdf_sync.synchronizer')->synchronize(SyncMethod::Delete, [$entity]);
        }

        \Drupal::database()->delete('rdf_sync_uri')
          ->condition('entity_id', $entity->id())
          ->execute();
      }
    }
  }
  $sandbox['current'] += count($ids);

  if ($sandbox['current'] >= $sandbox['total']) {
    return sprintf('Deletion of RDF storage for \"topic\" vocabulary completed. %d records removed.', $sandbox['total']);
  }
}
