<?php

/**
 * @file
 * Deploy hooks for Joinup i18n module.
 */

declare(strict_types=1);

/**
 * Locale source strings with duplicates.
 */
function joinup_i18n_deploy_201200(): void {
  // Duplicates of sources strings were detected. The root cause couldn't be
  // detected immediately, but the problem is deferred to ISAICP-10740.
  // @see https://citnet.tech.ec.europa.eu/CITnet/jira/browse/ISAICP-10740
  $db = \Drupal::database();
  $sql = <<<SQL
    SELECT GROUP_CONCAT(lid ORDER BY lid)
    FROM locales_source
    GROUP BY source, context
    HAVING COUNT(source) > 1
    SQL;

  $delete = [];
  foreach ($db->query($sql)->fetchCol() as $grouped) {
    $lids = explode(',', $grouped);
    array_shift($lids);
    $delete = [...$delete, ...$lids];
  }
  if ($delete) {
    $db->delete('locales_source')->condition('lid', $delete, 'IN')->execute();
    $db->delete('locales_location')->condition('sid', $delete, 'IN')->execute();
    $db->delete('locales_target')->condition('lid', $delete, 'IN')->execute();
    $db->delete('babel_source_instance')
      ->condition('plugin', 'locale')
      ->condition('id', $delete, 'IN')
      ->execute();
  }
}

/**
 * Queue path alias creation for all enabled languages on existing nodes.
 */
function joinup_i18n_deploy_201201(array &$sandbox): string {
  if (!isset($sandbox['nids'])) {
    $sandbox['nids'] = array_values(\Drupal::entityTypeManager()
      ->getStorage('node')->getQuery()
      ->accessCheck(FALSE)->execute());
    $sandbox['total'] = count($sandbox['nids']);
    $sandbox['processed'] = 0;
  }

  if (empty($sandbox['nids'])) {
    $sandbox['#finished'] = 1;
    return "No nodes to process.";
  }

  $batch_size = 500;
  $nids = array_splice($sandbox['nids'], 0, $batch_size);
  $queue = \Drupal::queue('joinup_i18n:translation_alias');

  foreach ($nids as $nid) {
    $queue->createItem([
      'entity_type_id' => 'node',
      'entity_id' => $nid,
    ]);
  }

  $sandbox['processed'] += count($nids);
  $sandbox['#finished'] = $sandbox['total'] ? $sandbox['processed'] / $sandbox['total'] : 1;

  return "Queued {$sandbox['processed']}/{$sandbox['total']} nodes for alias generation.";
}
