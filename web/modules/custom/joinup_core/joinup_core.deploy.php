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

use Drupal\joinup_core\Update\InlineImageConverter;

/**
 * Converts legacy inline <img> markup into <drupal-media> embeds.
 *
 * @see \Drupal\joinup_core\Update\InlineImageConverter
 */
function joinup_core_deploy_201602(array &$sandbox): string {
  /** @var \Drupal\joinup_core\Update\InlineImageConverter $converter */
  $converter = \Drupal::classResolver(InlineImageConverter::class);

  if (!isset($sandbox['items'])) {
    $sandbox['items'] = $converter->collect();
    $sandbox['total'] = count($sandbox['items']);
    $sandbox['stats'] = InlineImageConverter::STATS;
  }

  // Process a bounded slice per invocation: conversion loads and saves entities
  // that can be large, and creates media entities along the way.
  foreach (array_splice($sandbox['items'], 0, 25) as $item) {
    foreach ($converter->convert($item['entity_type'], $item['id']) as $key => $count) {
      $sandbox['stats'][$key] += $count;
    }
  }

  $remaining = count($sandbox['items']);
  $sandbox['#finished'] = $sandbox['total'] === 0 ? 1 : 1 - ($remaining / $sandbox['total']);

  $stats = $sandbox['stats'];
  return sprintf(
    'Inline images: %d converted on %d entities (%d media created, %d recovered from revisions). Skipped %d remote, %d base64, %d unrecoverable, %d missing file, %d without a group. Processed %d/%d entities.',
    $stats['converted'],
    $stats['entities'],
    $stats['media_created'],
    $stats['recovered_from_revision'],
    $stats['skipped_remote'],
    $stats['skipped_base64'],
    $stats['skipped_empty'],
    $stats['skipped_missing_file'],
    $stats['skipped_no_group'],
    $sandbox['total'] - $remaining,
    $sandbox['total']
  );
}
