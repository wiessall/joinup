<?php

/**
 * @file
 * Demo stopgap: writes what the demo needs as N-Triples, from SQL.
 *
 *   ddev drush php:script TRISTAN_additions/demo/agent-api/demo-export.php -- 7056 7066 > demo.nt
 *
 * For the given solutions:
 * - one sameInnovationAs claim per direction for every "same" decision that
 *   touches them (sti_dedupe_decision), shaped like the claims rdf_sync
 *   writes: solution hasFieldClaim c; c claimProperty sti:sameInnovationAs,
 *   claimRefValue other, claimedBy collection;
 * - skos:prefLabel for every claim property and value term their claims
 *   use, with the URI stored in rdf_sync_uri.
 *
 * Predicates use STI_NS (default the pre-P0 https://sti-portal.fao.org/ontology#),
 * so the claims join what the graph currently holds. Load into its own graph
 * (https://sti-portal.fao.org/graph/demo) and drop that graph after the full
 * rebuild, which writes all of this properly.
 */

declare(strict_types=1);

use Drupal\node\NodeInterface;

$seeds = array_values(array_filter(array_map('intval', $extra ?? [])));
if (!$seeds) {
  throw new \InvalidArgumentException('Pass one or more solution node ids after --.');
}
$ns = getenv('STI_NS') ?: 'https://sti-portal.fao.org/ontology#';
$skosPrefLabel = 'http://www.w3.org/2004/02/skos/core#prefLabel';
$sameAs = 'https://sti-portal.fao.org/sti#sameInnovationAs';

$database = \Drupal::database();
$uriOf = static function (string $type, array $ids) use ($database): array {
  if (!$ids) {
    return [];
  }
  return $database->select('rdf_sync_uri', 'u')
    ->fields('u', ['entity_id', 'uri'])
    ->condition('entity_type', $type)
    ->condition('entity_id', $ids, 'IN')
    ->execute()
    ->fetchAllKeyed();
};
$literal = static fn(string $value): string => '"' . addcslashes($value, "\\\"\n\r\t") . '"';
$out = [];

// Same-as pairs touching the seeds.
$query = $database->select('sti_dedupe_decision', 'd')->fields('d', ['nid_low', 'nid_high']);
$query->condition('decision', 'same');
$query->condition($query->orConditionGroup()->condition('nid_low', $seeds, 'IN')->condition('nid_high', $seeds, 'IN'));
$pairs = $query->execute()->fetchAll();

$nodes = \Drupal::entityTypeManager()->getStorage('node');
$solutionIds = $seeds;
foreach ($pairs as $pair) {
  $solutionIds[] = (int) $pair->nid_low;
  $solutionIds[] = (int) $pair->nid_high;
}
$solutionIds = array_values(array_unique($solutionIds));
$solutions = $nodes->loadMultiple($solutionIds);
$nodeUris = $uriOf('node', $solutionIds);

$collectionOf = [];
foreach ($solutions as $nid => $solution) {
  if ($solution instanceof NodeInterface && !$solution->get('og_audience')->isEmpty()) {
    $collectionOf[(int) $nid] = (int) $solution->get('og_audience')->target_id;
  }
}
$collectionUris = $uriOf('node', array_values(array_unique($collectionOf)));

$written = 0;
foreach ($pairs as $pair) {
  foreach ([[(int) $pair->nid_low, (int) $pair->nid_high], [(int) $pair->nid_high, (int) $pair->nid_low]] as [$subject, $object]) {
    if (!isset($nodeUris[$subject], $nodeUris[$object])) {
      fwrite(STDERR, "Skipping $subject -> $object: no stored URI.\n");
      continue;
    }
    $claim = "urn:sti-demo:same-as:$subject:$object";
    $out[] = "<{$nodeUris[$subject]}> <{$ns}hasFieldClaim> <$claim> .";
    $out[] = "<$claim> <{$ns}claimProperty> <$sameAs> .";
    $out[] = "<$claim> <{$ns}claimRefValue> <{$nodeUris[$object]}> .";
    if (isset($collectionOf[$subject], $collectionUris[$collectionOf[$subject]])) {
      $out[] = "<$claim> <{$ns}claimedBy> <{$collectionUris[$collectionOf[$subject]]}> .";
    }
    $written++;
  }
}

// Labels for the claim terms these solutions use.
$termIds = [];
foreach ($solutions as $solution) {
  if (!$solution instanceof NodeInterface || !$solution->hasField('field_claims')) {
    continue;
  }
  foreach ($solution->get('field_claims')->referencedEntities() as $claim) {
    foreach (['field_claim_property', 'field_claim_ref_value'] as $field) {
      if ($claim->hasField($field) && !$claim->get($field)->isEmpty()) {
        $termIds[] = (int) $claim->get($field)->target_id;
      }
    }
  }
}
$termIds = array_values(array_unique($termIds));
$termUris = $uriOf('taxonomy_term', $termIds);
$names = $termIds ? $database->select('taxonomy_term_field_data', 't')
  ->fields('t', ['tid', 'name'])
  ->condition('tid', $termIds, 'IN')
  ->execute()
  ->fetchAllKeyed() : [];
foreach ($termUris as $tid => $uri) {
  if (isset($names[$tid])) {
    $out[] = "<$uri> <$skosPrefLabel> " . $literal((string) $names[$tid]) . ' .';
  }
}

print implode("\n", $out) . "\n";
fwrite(STDERR, sprintf("%d same-as claim(s) for %d pair(s); %d term label(s); %d of %d terms have no stored URI.\n",
  $written, count($pairs), count($termUris), count($termIds) - count($termUris), count($termIds)));
