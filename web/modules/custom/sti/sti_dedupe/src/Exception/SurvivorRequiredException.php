<?php

declare(strict_types=1);

namespace Drupal\sti_dedupe\Exception;

/**
 * Thrown when a "same" decision would join two ATIO innovations.
 *
 * The reviewer must choose which one survives. Nothing has been written when
 * this is thrown: the decision is recorded only together with the link.
 */
final class SurvivorRequiredException extends \RuntimeException {

  /**
   * Constructs the exception.
   *
   * @param int[] $candidates
   *   The innovation node ids the reviewer must choose between.
   */
  public function __construct(public readonly array $candidates) {
    parent::__construct(sprintf('These solutions are on %d ATIO innovations (%s); choose the one that survives.', count($candidates), implode(', ', $candidates)));
  }

}
