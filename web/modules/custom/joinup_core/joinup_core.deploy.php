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

declare(strict_types = 1);

/**
 * Add state value to asset distributions.
 */
function joinup_core_deploy_110100(?array &$sandbox = NULL): string {
  /** @var \Drupal\sparql_entity_storage\SparqlEntityStorageInterface $storage */
  $storage = \Drupal::entityTypeManager()->getStorage('rdf_entity');

  if (!isset($sandbox['rids'])) {
    $query = $storage->getQuery()->accessCheck(FALSE);
    $query->condition('rid', ['asset_distribution'], 'IN');

    $sandbox['rids'] = $query->execute();
    $sandbox['total'] = count($sandbox['rids']);
    $sandbox['progress'] = 0;
  }

  $rids = array_splice($sandbox['rids'], 0, 50);
  /** @var \Drupal\asset_distribution\Entity\AssetDistributionInterface $rdf_entity */
  foreach ($storage->loadMultiple($rids) as $rdf_entity) {
    $rdf_entity->set('field_ad_state', 'published');
    $rdf_entity->skip_notification = TRUE;
    $rdf_entity->save();
  }

  $sandbox['progress'] += count($rids);
  $sandbox['#finished'] = (int) empty($sandbox['rids']);

  return "Processed {$sandbox['progress']} out of {$sandbox['total']} distributions";
}
