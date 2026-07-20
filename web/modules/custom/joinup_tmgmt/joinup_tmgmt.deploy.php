<?php

/**
 * @file
 * Deploy hooks for the Joinup TMGMT module.
 */

declare(strict_types=1);

use Drupal\pathauto\PathautoState;

/**
 * Normalizes node aliases mistranslated by TMGMT.
 */
function joinup_tmgmt_deploy_201500(array &$sandbox): string {
  $storage = \Drupal::entityTypeManager()->getStorage('node');

  if (!isset($sandbox['nids'])) {
    $bundles = [
      'collection',
      'custom_page',
      'discussion',
      'distribution',
      'document',
      'event',
      'news',
      'release',
      'solution',
    ];

    $query = \Drupal::database()->select('key_value', 'kv');
    $query->innerJoin('node_field_data', 'n', 'n.nid = kv.name');
    $query->addField('n', 'nid');
    $query->condition('kv.collection', 'pathauto_state.node');
    $query->condition('kv.value', serialize(PathautoState::SKIP));
    $query->condition('n.type', $bundles, 'IN');
    $query->distinct();
    $query->orderBy('n.nid');

    $sandbox['nids'] = $query->execute()->fetchCol();
    $sandbox['total'] = count($sandbox['nids']);
    $sandbox['progress'] = 0;
  }

  $nid = array_shift($sandbox['nids']);
  $sandbox['progress']++;

  $path_alias_storage = \Drupal::entityTypeManager()->getStorage('path_alias');
  $path_alias_cleaner = \Drupal::service('joinup_tmgmt.path_alias_cleaner');
  $node = $storage->load($nid);
  $path_aliases = $path_alias_storage->loadByProperties([
    'path' => '/node/' . $node->id(),
  ]);
  $updated = 0;
  foreach ($path_aliases as $path_alias) {
    $langcode = $path_alias->language()->getId();
    if ($langcode === 'en') {
      continue;
    }
    $path_alias->setAlias($path_alias_cleaner->cleanPath($path_alias->getAlias(), $langcode))
      ->save();
    $updated++;
  }

  $sandbox['#finished'] = (int) empty($sandbox['nids']);

  return sprintf(
    'Normalized %d Pathauto URL aliases for %s /node/%d, %d out of %d nodes.',
    $updated,
    $node->bundle(),
    $nid,
    $sandbox['progress'],
    $sandbox['total']
  );
}

/**
 * Regenerates URL aliases for subcontent of specific collections.
 */
function joinup_tmgmt_deploy_201501(array &$sandbox): string {
  $storage = \Drupal::entityTypeManager()->getStorage('node');

  if (!isset($sandbox['nids'])) {
    // Interoperable Europe, Assessments and OSS Catalogue collections.
    $collection_nids = [723208, 723215, 723282];
    $bundles = [
      'custom_page',
      'discussion',
      'distribution',
      'document',
      'event',
      'news',
      'release',
    ];

    $query = \Drupal::database()->select('node__og_audience', 'og');
    $query->addField('og', 'entity_id', 'nid');
    $query->innerJoin('key_value', 'kv', 'kv.name = og.entity_id');
    $query->condition('og.deleted', 0);
    $query->condition('og.og_audience_target_id', $collection_nids, 'IN');
    $query->condition('og.bundle', $bundles, 'IN');
    $query->condition('kv.collection', 'pathauto_state.node');
    $query->condition('kv.value', serialize(PathautoState::CREATE));
    $query->distinct();
    $query->orderBy('nid');

    $sandbox['nids'] = $query->execute()->fetchCol();
    $sandbox['total'] = count($sandbox['nids']);
    $sandbox['progress'] = 0;
  }

  $nid = array_shift($sandbox['nids']);
  $sandbox['progress']++;

  $path_alias_storage = \Drupal::entityTypeManager()->getStorage('path_alias');
  $pathauto_generator = \Drupal::service('pathauto.generator');
  $node = $storage->load($nid);
  $path_aliases = $path_alias_storage->loadByProperties([
    'path' => '/node/' . $node->id(),
  ]);
  $updated = 0;
  foreach ($path_aliases as $path_alias) {
    $langcode = $path_alias->language()->getId();
    if ($langcode === 'en' || !$node->hasTranslation($langcode)) {
      continue;
    }
    $translation = $node->getTranslation($langcode);
    $new_alias = $pathauto_generator->createEntityAlias($translation, 'return');
    if ($new_alias === NULL || $new_alias === $path_alias->getAlias()) {
      continue;
    }
    $path_alias->setAlias($new_alias)->save();
    $updated++;
  }

  $sandbox['#finished'] = (int) empty($sandbox['nids']);

  return sprintf(
    'Regenerated %d Pathauto URL aliases for %s /node/%d, %d out of %d nodes.',
    $updated,
    $node->bundle(),
    $nid,
    $sandbox['progress'],
    $sandbox['total']
  );
}
