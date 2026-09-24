<?php

declare(strict_types=1);

namespace Drupal\sti_dedupe;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\node\NodeInterface;
use Drupal\search_api\IndexInterface;

/**
 * Finds and scores possible duplicates of a solution.
 *
 * Scoring deliberately uses CORE fields only — title, owner, country, year.
 * It must not consume mapped claim values: that would make duplicate review
 * depend on vocabulary-mapping coverage, and ADR #14 requires that nothing
 * waits on a mapping.
 *
 * Weights are renormalised over the components that are actually comparable,
 * so a pair where neither record carries a year is not penalised for it.
 */
final class DuplicateFinder {

  /**
   * Fields tried in order when reading a solution's country.
   */
  private const COUNTRY_FIELDS = ['field_country_origin', 'country', 'spatial_coverage'];

  /**
   * Fields tried in order when reading a solution's start year.
   */
  private const YEAR_FIELDS = ['field_start_year', 'field_start_date'];

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly DecisionStore $decisions,
    private readonly LoggerChannelFactoryInterface $loggerFactory,
  ) {}

  /**
   * Returns scored candidates for one solution, best first.
   *
   * @param \Drupal\node\NodeInterface $solution
   *   The solution to find duplicates of.
   *
   * @return \Drupal\sti_dedupe\CandidateMatch[]
   *   Matches at or above the configured threshold.
   */
  public function findCandidates(NodeInterface $solution): array {
    $config = $this->configFactory->get('sti_dedupe.settings');
    $threshold = (float) ($config->get('threshold') ?? 0.8);
    $max = (int) ($config->get('max_candidates') ?? 5);

    $title = trim((string) $solution->label());
    if ($title === '') {
      return [];
    }

    $excluded = array_flip($this->decisions->decidedAgainst((int) $solution->id()));
    $seen = [];
    $matches = [];

    foreach ((array) ($config->get('indexes') ?? []) as $indexId) {
      foreach ($this->search($indexId, $solution, $title) as $candidate) {
        $nid = (int) $candidate->id();
        if (isset($seen[$nid]) || isset($excluded[$nid])) {
          continue;
        }
        $seen[$nid] = TRUE;

        [$score, $reasons] = $this->score($solution, $candidate);
        if ($score >= $threshold) {
          $matches[] = new CandidateMatch($candidate, $score, $reasons);
        }
      }
    }

    usort($matches, static fn(CandidateMatch $a, CandidateMatch $b): int => $b->score <=> $a->score);
    return array_slice($matches, 0, $max);
  }

  /**
   * Runs one index query and returns the candidate solutions it found.
   *
   * @return \Drupal\node\NodeInterface[]
   *   Candidate solutions, excluding the subject itself.
   */
  private function search(string $indexId, NodeInterface $solution, string $title): array {
    try {
      $index = $this->entityTypeManager->getStorage('search_api_index')->load($indexId);
    }
    catch (\Exception $e) {
      return [];
    }
    if (!$index instanceof IndexInterface || !$index->status()) {
      return [];
    }

    $nodes = [];
    try {
      $query = $index->query(['limit' => 50]);
      $query->keys($this->normalise($title));
      $query->setFulltextFields(['title']);
      $query->addCondition('entity_bundle', 'solution');
      $query->addCondition('nid', (int) $solution->id(), '<>');
      $result = $query->execute();

      foreach ($result->getResultItems() as $item) {
        try {
          $entity = $item->getOriginalObject()?->getValue();
        }
        catch (\Exception $e) {
          continue;
        }
        if ($entity instanceof NodeInterface && $entity->bundle() === 'solution') {
          $nodes[] = $entity;
        }
      }
    }
    catch (\Exception $e) {
      $this->loggerFactory->get('sti_dedupe')->warning(
        'Candidate search failed on index @index for node @nid: @message',
        [
          '@index' => $indexId,
          '@nid' => $solution->id(),
          '@message' => $e->getMessage(),
        ]
      );
    }

    return $nodes;
  }

  /**
   * Scores a pair on core fields.
   *
   * @return array{0: float, 1: string[]}
   *   The score between 0 and 1, and the reasons behind it.
   */
  public function score(NodeInterface $a, NodeInterface $b): array {
    $weights = (array) ($this->configFactory->get('sti_dedupe.settings')->get('weights') ?? []);
    $weights += ['title' => 0.6, 'owner' => 0.2, 'country' => 0.15, 'year' => 0.05];

    $applied = 0.0;
    $earned = 0.0;
    $reasons = [];

    // Title similarity is always comparable — both nodes have a label.
    $percent = 0.0;
    similar_text($this->normalise((string) $a->label()), $this->normalise((string) $b->label()), $percent);
    $titleScore = $percent / 100;
    $applied += (float) $weights['title'];
    $earned += (float) $weights['title'] * $titleScore;
    $reasons[] = 'title ' . number_format($titleScore, 2);

    $ownerA = $this->referenceId($a, ['owner']);
    $ownerB = $this->referenceId($b, ['owner']);
    if ($ownerA !== NULL && $ownerB !== NULL) {
      $applied += (float) $weights['owner'];
      if ($ownerA === $ownerB) {
        $earned += (float) $weights['owner'];
        $reasons[] = 'owner exact';
      }
      else {
        $reasons[] = 'owner differs';
      }
    }

    $countryA = $this->referenceId($a, self::COUNTRY_FIELDS);
    $countryB = $this->referenceId($b, self::COUNTRY_FIELDS);
    if ($countryA !== NULL && $countryB !== NULL) {
      $applied += (float) $weights['country'];
      if ($countryA === $countryB) {
        $earned += (float) $weights['country'];
        $reasons[] = 'country exact';
      }
      else {
        $reasons[] = 'country differs';
      }
    }

    $yearA = $this->year($a);
    $yearB = $this->year($b);
    if ($yearA !== NULL && $yearB !== NULL) {
      $applied += (float) $weights['year'];
      $delta = abs($yearA - $yearB);
      $proximity = max(0.0, 1.0 - ($delta / 5));
      $earned += (float) $weights['year'] * $proximity;
      $reasons[] = $delta === 0 ? 'year exact' : 'year ±' . $delta;
    }

    $score = $applied > 0 ? $earned / $applied : 0.0;
    return [round($score, 3), $reasons];
  }

  /**
   * Returns the first referenced entity id among the given fields.
   */
  private function referenceId(NodeInterface $node, array $fields): ?string {
    foreach ($fields as $field) {
      if (!$node->hasField($field) || $node->get($field)->isEmpty()) {
        continue;
      }
      $value = $node->get($field)->first()?->getValue();
      if (isset($value['target_id'])) {
        return (string) $value['target_id'];
      }
    }
    return NULL;
  }

  /**
   * Returns a comparable year, or NULL when neither field carries one.
   */
  private function year(NodeInterface $node): ?int {
    foreach (self::YEAR_FIELDS as $field) {
      if (!$node->hasField($field) || $node->get($field)->isEmpty()) {
        continue;
      }
      $raw = (string) ($node->get($field)->first()?->getValue()['value'] ?? '');
      if ($raw !== '' && preg_match('/(\d{4})/', $raw, $matches) === 1) {
        return (int) $matches[1];
      }
    }
    return NULL;
  }

  /**
   * Lower-cases, strips punctuation and collapses whitespace.
   */
  private function normalise(string $value): string {
    $value = mb_strtolower($value);
    $value = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $value) ?? $value;
    return trim(preg_replace('/\s+/', ' ', $value) ?? $value);
  }

}
