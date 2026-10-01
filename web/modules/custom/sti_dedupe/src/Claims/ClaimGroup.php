<?php

declare(strict_types=1);

namespace Drupal\sti_dedupe\Claims;

/**
 * Every claim an innovation's sources make about one exclusive property.
 */
final class ClaimGroup {

  /**
   * Constructs a ClaimGroup.
   *
   * @param string $atioProperty
   *   The single-valued ATIO property.
   * @param array<string, \Drupal\sti_dedupe\Claims\ClaimTuple> $tuples
   *   Distinct claims, keyed by ClaimTuple::key().
   */
  public function __construct(
    public readonly string $atioProperty,
    public readonly array $tuples,
  ) {}

  /**
   * The number of distinct values claimed, across all collections.
   */
  public function distinctValues(): int {
    return count(array_unique(array_map(static fn(ClaimTuple $tuple): string => $tuple->valueKey, $this->tuples)));
  }

  /**
   * Whether the sources disagree, which is what makes a conflict.
   */
  public function isConflicting(): bool {
    return $this->distinctValues() > 1;
  }

}
