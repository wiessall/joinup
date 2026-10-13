<?php

declare(strict_types=1);

namespace Drupal\sti_graph_explorer\Sparql;

/**
 * The rows of a SELECT query, and whether the store cut them short.
 */
final class SelectResult {

  /**
   * @param list<array<string, \Drupal\sti_graph_explorer\Sparql\Term>> $rows
   *   One array per solution, keyed by variable name. Unbound variables are
   *   absent from the array.
   * @param bool $partial
   *   TRUE when the store returned a truncated result without failing.
   * @param string $partialReason
   *   The store's explanation, if it gave one.
   */
  public function __construct(
    public readonly array $rows,
    public readonly bool $partial = FALSE,
    public readonly string $partialReason = '',
  ) {}

}
