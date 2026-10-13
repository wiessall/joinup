<?php

declare(strict_types=1);

namespace Drupal\sti_dedupe\Claims;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\node\NodeInterface;
use Drupal\sti_dedupe\ContentModel;
use Drupal\taxonomy\TermInterface;

/**
 * Reads the claims of solutions as comparable tuples.
 *
 * Only claims whose property term is mapped to an ATIO property
 * (field_atio_property) are returned. An unmapped property is never compared:
 * nothing waits on a mapping, and nothing is inferred from a label match on
 * property names across collections.
 *
 * Values compare by their SKOS exact match when the value term has one, and
 * by normalised label otherwise. Two collections writing "TRL 5" and
 * "trl-5" agree; "TRL 5" mapped to a CGIAR concept and "TRL 5" unmapped do
 * not, which surfaces the missing mapping as a conflict instead of hiding it.
 */
final class ClaimReader {

  /**
   * Terms loaded during this request, keyed by tid.
   *
   * @var array<int, \Drupal\taxonomy\TermInterface|null>
   */
  private array $terms = [];

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Returns the mapped claims made on the given solutions.
   *
   * The current revision of each solution is read: conflicts concern what
   * the sources say now, whatever revision an innovation pinned.
   *
   * @param int[] $solutionNids
   *   Solution node ids.
   *
   * @return \Drupal\sti_dedupe\Claims\ClaimTuple[]
   *   Claims in source order; duplicates are not removed here.
   */
  public function forSolutions(array $solutionNids): array {
    $tuples = [];
    foreach ($this->entityTypeManager->getStorage('node')->loadMultiple($solutionNids) as $solution) {
      if (!$solution instanceof NodeInterface || !$solution->hasField(ContentModel::CLAIMS)) {
        continue;
      }
      foreach ($solution->get(ContentModel::CLAIMS)->referencedEntities() as $claim) {
        $tuple = $this->tuple($claim);
        if ($tuple !== NULL) {
          $tuples[] = $tuple;
        }
      }
    }
    return $tuples;
  }

  /**
   * Builds a tuple from one claim paragraph, or NULL when it is not usable.
   */
  private function tuple(FieldableEntityInterface $claim): ?ClaimTuple {
    foreach ([ContentModel::CLAIMED_BY, ContentModel::CLAIM_PROPERTY] as $field) {
      if (!$claim->hasField($field) || $claim->get($field)->isEmpty()) {
        return NULL;
      }
    }

    $property = $this->term((int) $claim->get(ContentModel::CLAIM_PROPERTY)->target_id);
    if ($property === NULL || !$property->hasField(ContentModel::ATIO_PROPERTY) || $property->get(ContentModel::ATIO_PROPERTY)->isEmpty()) {
      return NULL;
    }
    $atioProperty = trim((string) $property->get(ContentModel::ATIO_PROPERTY)->value);

    $value = $this->value($claim);
    if ($value === NULL) {
      return NULL;
    }

    return new ClaimTuple(
      (int) $claim->get(ContentModel::CLAIMED_BY)->target_id,
      (int) $property->id(),
      $atioProperty,
      $value[0],
      $value[1],
    );
  }

  /**
   * Returns [valueKey, valueLabel] for a claim, or NULL when it has no value.
   *
   * @return array{0: string, 1: string}|null
   *   The comparable key and the display label.
   */
  private function value(FieldableEntityInterface $claim): ?array {
    if ($claim->hasField(ContentModel::CLAIM_REF_VALUE) && !$claim->get(ContentModel::CLAIM_REF_VALUE)->isEmpty()) {
      $term = $this->term((int) $claim->get(ContentModel::CLAIM_REF_VALUE)->target_id);
      if ($term === NULL) {
        return NULL;
      }
      $label = (string) $term->label();
      if ($term->hasField(ContentModel::EXACT_MATCH) && !$term->get(ContentModel::EXACT_MATCH)->isEmpty()) {
        return ['uri:' . trim((string) $term->get(ContentModel::EXACT_MATCH)->value), $label];
      }
      return ['label:' . self::normalise($label), $label];
    }

    if ($claim->hasField(ContentModel::CLAIM_LITERAL) && !$claim->get(ContentModel::CLAIM_LITERAL)->isEmpty()) {
      $label = (string) $claim->get(ContentModel::CLAIM_LITERAL)->value;
      return trim($label) === '' ? NULL : ['label:' . self::normalise($label), $label];
    }

    return NULL;
  }

  /**
   * Loads a term once per request.
   */
  private function term(int $tid): ?TermInterface {
    if (!array_key_exists($tid, $this->terms)) {
      $term = $this->entityTypeManager->getStorage('taxonomy_term')->load($tid);
      $this->terms[$tid] = $term instanceof TermInterface ? $term : NULL;
    }
    return $this->terms[$tid];
  }

  /**
   * Lower-cases, strips punctuation and collapses whitespace.
   */
  public static function normalise(string $value): string {
    $value = mb_strtolower($value);
    $value = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $value) ?? $value;
    return trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
  }

}
