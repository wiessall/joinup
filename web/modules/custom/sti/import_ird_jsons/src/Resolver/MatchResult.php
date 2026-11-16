<?php

declare(strict_types=1);

namespace Drupal\import_ird_jsons\Resolver;

/**
 * What a lookup found.
 *
 * $id is set only for an accepted match. 'ambiguous' carries the candidates a second step (a person, an LLM) can
 * choose from; 'none' means nothing came close.
 */
final class MatchResult {

  public const CODE = 'code';
  public const KEY = 'key';
  public const ACRONYM = 'acronym';
  public const FUZZY = 'fuzzy';
  public const AMBIGUOUS = 'ambiguous';
  public const NONE = 'none';

  /**
   * @param array<string, float> $candidates
   *   Candidate ids and their scores, best first.
   */
  public function __construct(
    public readonly ?string $id,
    public readonly string $tier,
    public readonly float $score = 0.0,
    public readonly array $candidates = [],
  ) {}

}
