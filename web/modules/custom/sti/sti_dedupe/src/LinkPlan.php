<?php

declare(strict_types=1);

namespace Drupal\sti_dedupe;

/**
 * What a "same" decision on a pair would do, computed before doing it.
 */
final class LinkPlan {

  /**
   * Constructs a LinkPlan.
   *
   * @param int[] $cluster
   *   Every solution that would be linked, the pair included.
   * @param int[] $innovations
   *   Live ATIO innovations derived from any of them.
   */
  public function __construct(
    public readonly array $cluster,
    public readonly array $innovations,
  ) {}

  /**
   * Whether the reviewer has to choose a surviving innovation.
   */
  public function needsSurvivor(): bool {
    return count($this->innovations) > 1;
  }

}
