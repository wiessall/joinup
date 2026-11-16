<?php

declare(strict_types=1);

namespace Drupal\import_ird_jsons\Resolver;

/**
 * In-memory index of entities by name and acronym, with a tiered lookup. No Drupal dependency.
 *
 * Tiers, in order:
 *   key      the input's name, normalised, equals one entity's name (case, accents, punctuation, legal suffix,
 *            stopwords and an embedded acronym ignored)
 *   acronym  the input is, or carries, an acronym that exactly one entity has, and the names don't contradict it
 *   fuzzy    one entity's name is similar enough, and clearly more similar than the next best
 * Two entities on the same key or acronym is 'ambiguous', never a guess.
 */
final class CandidateIndex {

  /** @var array<string, list<string>> */
  private array $byKey = [];

  /** @var array<string, list<string>> */
  private array $byAcronym = [];

  /** @var array<string, list<string>> */
  private array $byToken = [];

  /** @var array<string, list<string>> */
  private array $keysOf = [];

  /** @var array<string, list<string>> */
  private array $qualifiersOf = [];

  /** @var array<string, list<string>> */
  private array $byCode = [];

  /**
   * @param float $threshold
   *   Minimum similarity for a fuzzy match.
   * @param float $margin
   *   How much better the best fuzzy candidate must be than the second.
   * @param float $floor
   *   Below this, a candidate is not worth showing a second step.
   */
  public function __construct(
    private readonly float $threshold = 0.9,
    private readonly float $margin = 0.05,
    private readonly float $floor = 0.6,
  ) {}

  /**
   * Adds an entity under its names, acronyms and codes.
   *
   * @param string[] $names
   * @param string[] $acronyms
   * @param string[] $codes
   *   Identifiers matched only exactly, any length: ISO 3166 "KE", "KEN", M49 "404".
   */
  public function add(string $id, array $names, array $acronyms = [], array $codes = []): void {
    foreach ($codes as $code) {
      if (($code = strtoupper(trim((string) $code))) !== '') {
        $this->push($this->byCode, $code, $id);
      }
    }
    foreach ($names as $name) {
      [$bare, $embedded] = NameMatcher::splitAcronym($name);
      if ($embedded !== NULL) {
        $acronyms[] = $embedded;
      }
      [$bare, $qualifiers] = NameMatcher::splitQualifiers($bare);
      $this->qualifiersOf[$id] = array_values(array_unique([...$this->qualifiersOf[$id] ?? [], ...$qualifiers]));
      // The name alone, and with its qualifier words: "Iran (Islamic Republic of)" is then also found as
      // "Iran, Islamic Republic of" or "Islamic Republic of Iran".
      foreach (array_unique([NameMatcher::key($bare), NameMatcher::key($bare . ' ' . implode(' ', $qualifiers))]) as $key) {
        if ($key === '') {
          continue;
        }
        $this->push($this->byKey, $key, $id);
        $this->keysOf[$id][] = $key;
        foreach (array_unique(explode(' ', $key)) as $token) {
          $this->push($this->byToken, $token, $id);
        }
      }
    }
    foreach ($acronyms as $acronym) {
      $a = NameMatcher::acronymKey($acronym);
      if (strlen($a) >= 3) {
        $this->push($this->byAcronym, $a, $id);
      }
    }
  }

  /**
   * Removes an entity, for tests that hold one out.
   */
  public function remove(string $id): void {
    foreach ([&$this->byKey, &$this->byAcronym, &$this->byToken, &$this->byCode] as &$map) {
      foreach ($map as $k => $ids) {
        $map[$k] = array_values(array_diff($ids, [$id]));
        if (!$map[$k]) {
          unset($map[$k]);
        }
      }
    }
    unset($this->keysOf[$id], $this->qualifiersOf[$id]);
  }

  public function resolve(string $input): MatchResult {
    // A code is taken only as typed: upper case, two or three characters ("KE", "KEN"), so words like "in" or
    // "us" in lower case are not read as India or the United States.
    $code = trim($input);
    if (preg_match('/^[A-Z0-9]{2,3}$/', $code) && count($this->byCode[$code] ?? []) === 1) {
      return new MatchResult($this->byCode[$code][0], MatchResult::CODE, 1.0);
    }

    [$bare, $acronym] = NameMatcher::splitAcronym($input);
    [$bare, $qualifiers] = NameMatcher::splitQualifiers($bare);
    $key = NameMatcher::key($bare);
    if ($key === '') {
      return new MatchResult(NULL, MatchResult::NONE);
    }

    // Qualifiers decide between same-named entities and rule out conflicting ones: "Ministry of Agriculture
    // (Kenya)" is not the Sri Lankan one. An entity without qualifiers conflicts with nothing.
    $compatible = fn(string $id): bool => !$qualifiers || !($this->qualifiersOf[$id] ?? [])
      || array_intersect($qualifiers, $this->qualifiersOf[$id]);

    // A generic name ("Forest Department") is accepted only on an exact name whose qualifiers agree, or where
    // neither side has any. Anything else goes to a second step with its candidates.
    $generic = NameMatcher::isGeneric($key);
    if ($generic) {
      $exact = $this->byKey[$key] ?? [];
      $agree = array_values(array_filter($exact, fn($id) => $qualifiers
        ? (bool) array_intersect($qualifiers, $this->qualifiersOf[$id] ?? [])
        : !($this->qualifiersOf[$id] ?? [])));
      if (count($agree) === 1) {
        return new MatchResult($agree[0], MatchResult::KEY, 1.0);
      }
      $top = array_slice(array_filter($this->score($key), fn($id) => $compatible((string) $id), ARRAY_FILTER_USE_KEY), 0, 5, TRUE);
      return $top
        ? new MatchResult(NULL, MatchResult::AMBIGUOUS, (float) reset($top), $top)
        : new MatchResult(NULL, MatchResult::NONE);
    }

    $exact = array_values(array_filter($this->byKey[$key] ?? [], $compatible));
    if (count($exact) > 1 && $qualifiers) {
      $exact = array_values(array_filter($exact, fn($id) => array_intersect($qualifiers, $this->qualifiersOf[$id] ?? [])))
        ?: $exact;
    }
    if (count($exact) === 1) {
      return new MatchResult($exact[0], MatchResult::KEY, 1.0);
    }
    if (count($exact) > 1) {
      return new MatchResult(NULL, MatchResult::AMBIGUOUS, 1.0, array_fill_keys($exact, 1.0));
    }

    $scores = array_filter($this->score($key), fn($id) => $compatible((string) $id), ARRAY_FILTER_USE_KEY);

    // Acronym: the input is an acronym ("IFAD") or carries one ("... (IFAD)").
    $acr = NameMatcher::acronymKey($acronym ?? (NameMatcher::looksLikeAcronym($bare) ? $bare : ''));
    $byAcr = strlen($acr) >= 3 ? ($this->byAcronym[$acr] ?? []) : [];
    if (count($byAcr) === 1) {
      $id = $byAcr[0];
      // A name next to the acronym must not point elsewhere: "CARDI" alone is fine, "Cambodian ... (CARDI)"
      // must not land on the Caribbean CARDI.
      $nameAgrees = $acronym === NULL || $this->covers($key, $id);
      if ($nameAgrees) {
        return new MatchResult($id, MatchResult::ACRONYM, $scores[$id] ?? 0.0, [$id => $scores[$id] ?? 0.0]);
      }
    }

    $best = array_key_first($scores);
    if ($best === NULL || $scores[$best] < $this->floor) {
      return new MatchResult(NULL, MatchResult::NONE, $best === NULL ? 0.0 : $scores[$best]);
    }
    $second = array_values($scores)[1] ?? 0.0;
    $top = array_slice($scores, 0, 5, TRUE);
    foreach ($byAcr as $id) {
      $top[$id] ??= $scores[$id] ?? 0.0;
    }
    if ($scores[$best] >= $this->threshold && $scores[$best] - $second >= $this->margin && $this->covers($key, (string) $best)) {
      return new MatchResult((string) $best, MatchResult::FUZZY, $scores[$best], $top);
    }
    return new MatchResult(NULL, MatchResult::AMBIGUOUS, $scores[$best], $top);
  }

  /**
   * Scores every entity sharing a word with the key, best first.
   *
   * @return array<string, float>
   */
  private function score(string $key): array {
    $candidates = [];
    foreach (array_unique(explode(' ', $key)) as $token) {
      foreach ($this->byToken[$token] ?? [] as $id) {
        $candidates[$id] = TRUE;
      }
    }
    $scores = [];
    foreach (array_keys($candidates) as $id) {
      $scores[(string) $id] = max(array_map(fn($k) => NameMatcher::similarity($key, $k), $this->keysOf[$id]));
    }
    arsort($scores);
    return $scores;
  }

  /**
   * Whether every word of the key and of one of the entity's names has a close counterpart in the other:
   * "Bioscience" is not "Biosaline", "Cambodian" is not "Caribbean".
   */
  private function covers(string $key, string $id): bool {
    foreach ($this->keysOf[$id] ?? [] as $k) {
      if (min(NameMatcher::coverage($key, $k), NameMatcher::coverage($k, $key)) >= 1.0) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * @param array<string, list<string>> $map
   */
  private function push(array &$map, string $k, string $id): void {
    if (!in_array($id, $map[$k] ?? [], TRUE)) {
      $map[$k][] = $id;
    }
  }

}
