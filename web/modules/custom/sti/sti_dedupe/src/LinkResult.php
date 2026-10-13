<?php

declare(strict_types=1);

namespace Drupal\sti_dedupe;

/**
 * What a "same" decision did.
 */
final class LinkResult {

  /**
   * Constructs a LinkResult.
   *
   * @param int[] $cluster
   *   The solutions now known to be the same innovation.
   * @param int|null $innovation
   *   The innovation they are all derived from, or NULL when none exists yet
   *   and only the decision and the claims were recorded.
   * @param int[] $added
   *   Solutions newly added to that innovation's derived-from list.
   * @param int[] $replaced
   *   Innovations unpublished in favour of the survivor.
   * @param bool $conflictsRefreshed
   *   FALSE when the claim conflict refresh failed and was queued instead.
   */
  public function __construct(
    public readonly array $cluster,
    public readonly ?int $innovation,
    public readonly array $added = [],
    public readonly array $replaced = [],
    public readonly bool $conflictsRefreshed = TRUE,
  ) {}

}
