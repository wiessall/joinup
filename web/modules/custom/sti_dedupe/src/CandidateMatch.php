<?php

declare(strict_types=1);

namespace Drupal\sti_dedupe;

use Drupal\node\NodeInterface;

/**
 * One scored duplicate candidate for a solution.
 */
final class CandidateMatch {

  /**
   * Constructs a CandidateMatch.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The candidate solution.
   * @param float $score
   *   The match score, between 0 and 1.
   * @param string[] $reasons
   *   Human-readable scoring breakdown, e.g. "title 0.94", "owner exact".
   */
  public function __construct(
    public readonly NodeInterface $node,
    public readonly float $score,
    public readonly array $reasons = [],
  ) {}

  /**
   * Returns the reasons as one comma-free, dot-separated string.
   */
  public function reasonsAsString(): string {
    return implode(' · ', $this->reasons);
  }

}
