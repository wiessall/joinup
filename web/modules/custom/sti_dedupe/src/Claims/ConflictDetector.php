<?php

declare(strict_types=1);

namespace Drupal\sti_dedupe\Claims;

use Drupal\node\NodeInterface;
use Drupal\sti_dedupe\ContentModel;

/**
 * Groups an innovation's source claims by exclusive ATIO property.
 *
 * Read-only. Whether a group is a conflict is ClaimGroup::isConflicting();
 * what to do about it is ResolutionReconciler's job.
 */
final class ConflictDetector {

  public function __construct(
    private readonly ClaimReader $reader,
    private readonly ExclusiveProperties $exclusive,
  ) {}

  /**
   * Returns one group per exclusive property that any source claims.
   *
   * @return array<string, \Drupal\sti_dedupe\Claims\ClaimGroup>
   *   Keyed by ATIO property URI, sorted by key.
   */
  public function groups(NodeInterface $innovation): array {
    if (!$innovation->hasField(ContentModel::DERIVED_FROM)) {
      return [];
    }
    $solutionNids = array_map(
      static fn(array $item): int => (int) $item['target_id'],
      $innovation->get(ContentModel::DERIVED_FROM)->getValue(),
    );

    $byProperty = [];
    foreach ($this->reader->forSolutions($solutionNids) as $tuple) {
      if ($this->exclusive->isExclusive($tuple->atioProperty)) {
        $byProperty[$tuple->atioProperty][$tuple->key()] = $tuple;
      }
    }
    ksort($byProperty);

    $groups = [];
    foreach ($byProperty as $property => $tuples) {
      ksort($tuples);
      $groups[$property] = new ClaimGroup($property, $tuples);
    }
    return $groups;
  }

}
