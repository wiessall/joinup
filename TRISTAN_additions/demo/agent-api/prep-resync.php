<?php

/**
 * @file
 * Demo prep: re-syncs a few solutions and everything the queries touch.
 *
 * Usage, with solution node ids:
 *   ddev drush php:script TRISTAN_additions/demo/agent-api/prep-resync.php -- 123 456
 *
 * Saves, in this order, so rdf_sync rewrites each entity's triples under
 * its stored URI with the current (P0) predicates and classes:
 * - the given solutions plus every solution they are sameInnovationAs;
 * - all claim paragraphs on those solutions;
 * - the claim property and value terms those claims use (labels);
 * - the solutions themselves and their collections.
 *
 * Local demo tooling, not a data repair: it changes nothing in SQL, it only
 * re-sends what SQL already holds. No new revisions are created.
 */

declare(strict_types=1);

use Drupal\node\NodeInterface;
use Drupal\paragraphs\ParagraphInterface;

$seeds = array_values(array_filter(array_map('intval', $extra ?? [])));
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

$saved = ['paragraph' => 0, 'taxonomy_term' => 0, 'node' => 0];
$failed = [];
$save = static function ($entity) use (&$saved, &$failed): void {
  try {
    $entity->save();
    $saved[$entity->getEntityTypeId()]++;
  }
  catch (\Throwable $e) {
    $failed[] = sprintf('%s %s: %s', $entity->getEntityTypeId(), $entity->id(), $e->getMessage());
  }
};

$termIds = [];
$collectionIds = [];
foreach ($nodes->loadMultiple($solutionIds) as $solution) {
  if (!$solution instanceof NodeInterface || $solution->bundle() !== 'solution') {
    $failed[] = "node {$solution?->id()}: not a solution";
    continue;
  }
  foreach ($solution->get('field_claims')->referencedEntities() as $claim) {
    assert($claim instanceof ParagraphInterface);
    $save($claim);
    foreach (['field_claim_property', 'field_claim_ref_value'] as $field) {
      if ($claim->hasField($field) && !$claim->get($field)->isEmpty()) {
        $termIds[] = (int) $claim->get($field)->target_id;
      }
    }
  }
  if (!$solution->get('og_audience')->isEmpty()) {
    $collectionIds[] = (int) $solution->get('og_audience')->target_id;
  }
}
foreach ($terms->loadMultiple(array_unique($termIds)) as $term) {
  $save($term);
}
foreach ($nodes->loadMultiple(array_merge($solutionIds, array_unique($collectionIds))) as $node) {
  $save($node);
}

printf("Solutions: %s\nSaved: %d claim(s), %d term(s), %d node(s).\n",
  implode(', ', $solutionIds), $saved['paragraph'], $saved['taxonomy_term'], $saved['node']);
if ($failed) {
  fwrite(STDERR, "Failed:\n  " . implode("\n  ", $failed) . "\n");
  exit(1);
}
