<?php

/**
 * @file
 * Scores the resolver against a reviewed set of lookups. Plain PHP, no Drupal.
 *
 * Usage: php scripts/eval_resolver.php resolver_eval.json [threshold] [margin]
 *
 * The file holds "index" (entities: id, names, acronyms) and "queries" (input, expected id or null, kind,
 * optionally "exclude": an id held out of the index for that query).
 */

declare(strict_types=1);

spl_autoload_register(function (string $class): void {
  $prefix = 'Drupal\\import_ird_jsons\\';
  if (str_starts_with($class, $prefix)) {
    require __DIR__ . '/../src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
  }
});

use Drupal\import_ird_jsons\Resolver\CandidateIndex;
use Drupal\import_ird_jsons\Resolver\MatchResult;

$data = json_decode(file_get_contents($argv[1] ?? 'resolver_eval.json'), TRUE, flags: JSON_THROW_ON_ERROR);
$index = new CandidateIndex((float) ($argv[2] ?? 0.9), (float) ($argv[3] ?? 0.05));
$entities = [];
foreach ($data['index'] as $e) {
  $index->add((string) $e['id'], $e['names'], $e['acronyms'], $e['codes'] ?? []);
  $entities[(string) $e['id']] = $e;
}

$stats = [];
$wrong = [];
foreach ($data['queries'] as $q) {
  $held = $q['exclude'] ?? NULL;
  if ($held !== NULL) {
    $index->remove($held);
  }
  $r = $index->resolve($q['input']);
  if ($held !== NULL && isset($entities[$held])) {
    $index->add($held, $entities[$held]['names'], $entities[$held]['acronyms'], $entities[$held]['codes'] ?? []);
  }
  $expected = $q['expected'] === NULL ? NULL : (string) $q['expected'];
  $outcome = match (TRUE) {
    $r->id !== NULL && $r->id === $expected => 'right',
    $r->id !== NULL => 'WRONG',
    $expected === NULL => 'right (none)',
    $r->tier === MatchResult::AMBIGUOUS && isset($r->candidates[$expected]) => 'for 2nd step',
    default => 'missed',
  };
  $stats[$q['kind']][$r->tier][$outcome] = ($stats[$q['kind']][$r->tier][$outcome] ?? 0) + 1;
  if ($outcome === 'WRONG') {
    $wrong[] = sprintf('  %-8s %-55s -> %s %s (expected %s)', $q['kind'], mb_substr($q['input'], 0, 55), $r->tier,
      $entities[$r->id]['names'][0] ?? $r->id, $expected === NULL ? 'none' : ($entities[$expected]['names'][0] ?? $expected));
  }
}

foreach ($stats as $kind => $tiers) {
  echo "$kind\n";
  foreach ($tiers as $tier => $outcomes) {
    ksort($outcomes);
    echo sprintf("  %-10s %s\n", $tier, implode(', ', array_map(fn($k, $v) => "$k $v", array_keys($outcomes), $outcomes)));
  }
}
echo "\nwrong matches (" . count($wrong) . "):\n" . implode("\n", $wrong) . "\n";
