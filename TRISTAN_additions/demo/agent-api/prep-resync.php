<?php

/**
 * @file
 * Demo prep: re-syncs a few solutions and everything the queries touch.
 *
 * Usage, with solution node ids:
 *   ddev drush php:script TRISTAN_additions/demo/agent-api/prep-resync.php -- 123 456
 *
 * Re-saves, so rdf_sync rewrites each entity's triples under its stored URI
 * with the current (P0) predicates and classes, for the given solutions plus
 * every solution they are sameInnovationAs. In this order: the claim
 * property and value terms their claims use, the claim paragraphs, then the
 * solutions and their collections.
 *
 * Local demo tooling, not a data repair: it changes nothing in SQL, it only
 * re-sends what SQL already holds. No new revisions are created.
 *
 * Preflight: rdf_sync writes the graph at the end of the request, and one
 * entity whose own URI, or whose referenced entity's URI, is empty fails the
 * whole batch ("$uri should be a string and cannot be null or empty"). So
 * before saving anything, every entity to be saved and every entity it
 * references through a 'resource' mapping is checked for a stored URI; the
 * script stops and lists the gaps instead of half-syncing.
 *
 *   ... prep-resync.php -- --check 123 456   preflight only, saves nothing
 */

declare(strict_types=1);

use Drupal\node\NodeInterface;
use Drupal\paragraphs\ParagraphInterface;

$args = $extra ?? [];
$checkOnly = in_array('--check', $args, TRUE);
$seeds = array_values(array_filter(array_map('intval', array_diff($args, ['--check']))));
if (!$seeds) {
  throw new \InvalidArgumentException('Pass one or more solution node ids after --.');
}

$entityTypeManager = \Drupal::entityTypeManager();
$nodes = $entityTypeManager->getStorage('node');
$terms = $entityTypeManager->getStorage('taxonomy_term');
/** @var \Drupal\sti_dedupe\SameAsClaims $sameAs */
$sameAs = \Drupal::service('sti_dedupe.same_as_claims');

// Seeds plus their duplicates.
$solutionIds = $seeds;
foreach ($nodes->loadMultiple($seeds) as $seed) {
  if ($seed instanceof NodeInterface) {
    $solutionIds = array_merge($solutionIds, $sameAs->objectsOf($seed));
  }
}
$solutionIds = array_values(array_unique($solutionIds));

// Collect what will be saved: claims, their terms, solutions, collections.
$toSave = [];
$collectionIds = [];
foreach ($nodes->loadMultiple($solutionIds) as $solution) {
  if (!$solution instanceof NodeInterface || $solution->bundle() !== 'solution') {
    fwrite(STDERR, "Skipping node {$solution?->id()}: not a solution.\n");
    continue;
  }
  $termIds = [];
  foreach ($solution->get('field_claims')->referencedEntities() as $claim) {
    assert($claim instanceof ParagraphInterface);
    $toSave['paragraph:' . $claim->id()] = $claim;
    foreach (['field_claim_property', 'field_claim_ref_value'] as $field) {
      if ($claim->hasField($field) && !$claim->get($field)->isEmpty()) {
        $termIds[] = (int) $claim->get($field)->target_id;
      }
    }
  }
  foreach ($terms->loadMultiple(array_unique($termIds)) as $term) {
    $toSave['taxonomy_term:' . $term->id()] = $term;
  }
  $toSave['node:' . $solution->id()] = $solution;
  if (!$solution->get('og_audience')->isEmpty()) {
    $collectionIds[] = (int) $solution->get('og_audience')->target_id;
  }
}
foreach ($nodes->loadMultiple(array_unique($collectionIds)) as $collection) {
  $toSave['node:' . $collection->id()] = $collection;
}

// Referenced before referencing: terms, then claims, then nodes.
$order = ['taxonomy_term' => 0, 'paragraph' => 1, 'node' => 2];
uksort($toSave, static fn(string $a, string $b): int =>
  $order[strtok($a, ':')] <=> $order[strtok($b, ':')] ?: strnatcmp($a, $b));

// Preflight.
$database = \Drupal::database();
$mappingReader = \Drupal::service('sti_rdf.mapping_reader');
$mapper = \Drupal::service('rdf_sync.mapper');
$hasUri = static function ($entity) use ($database, $mapper): bool {
  if (!$mapper->isMappedEntity($entity)) {
    // Not projected: rdf_sync falls back to the raw target id, not a URI.
    return TRUE;
  }
  $stored = $database->select('rdf_sync_uri', 'u')
    ->fields('u', ['uri'])
    ->condition('entity_type', $entity->getEntityTypeId())
    ->condition('entity_id', $entity->id())
    ->execute()
    ->fetchField();
  return is_string($stored) && $stored !== '';
};
$gaps = [];
foreach ($toSave as $key => $entity) {
  $bundleType = $entity->getEntityType()->getBundleEntityType();
  $bundleEntity = $bundleType ? $entityTypeManager->getStorage($bundleType)->load($entity->bundle()) : NULL;
  $uriPlugin = $bundleEntity?->getThirdPartySetting('rdf_sync', 'uri_plugin');
  if (!$hasUri($entity) && !$uriPlugin) {
    $gaps[] = "$key ({$entity->bundle()}): no stored URI and no URI plugin to mint one";
  }
  foreach ($mappingReader->fieldMappings($entity->getEntityTypeId(), $entity->bundle()) as $mapping) {
    if ($mapping['type'] !== 'resource' || !$entity->hasField($mapping['field'])) {
      continue;
    }
    foreach ($entity->get($mapping['field']) as $delta => $item) {
      $target = $item->entity ?? NULL;
      if ($target === NULL || isset($toSave[$target->getEntityTypeId() . ':' . $target->id()])) {
        // Saved in this run: checked as a subject above.
        continue;
      }
      if (!$hasUri($target)) {
        $gaps[] = sprintf('%s field %s[%d] -> %s:%s (%s): mapped, but no stored URI',
          $key, $mapping['field'], $delta, $target->getEntityTypeId(), $target->id(), $target->bundle());
      }
    }
  }
}

printf("Solutions: %s\nTo save: %d entities.\n", implode(', ', $solutionIds), count($toSave));
if ($gaps) {
  fwrite(STDERR, "Preflight found references that would fail the graph write:\n  " . implode("\n  ", array_unique($gaps)) . "\nNothing was saved.\n");
  exit(1);
}
if ($checkOnly) {
  print "Preflight clean. Nothing saved (--check).\n";
  return;
}

$saved = ['paragraph' => 0, 'taxonomy_term' => 0, 'node' => 0];
$failed = [];
foreach ($toSave as $key => $entity) {
  try {
    $entity->save();
    $saved[$entity->getEntityTypeId()]++;
  }
  catch (\Throwable $e) {
    $failed[] = "$key: " . $e->getMessage();
  }
}
printf("Saved: %d claim(s), %d term(s), %d node(s). The graph is written when this script ends.\n",
  $saved['paragraph'], $saved['taxonomy_term'], $saved['node']);
if ($failed) {
  fwrite(STDERR, "Failed:\n  " . implode("\n  ", $failed) . "\n");
  exit(1);
}
