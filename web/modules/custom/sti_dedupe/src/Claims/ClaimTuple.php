<?php

declare(strict_types=1);

namespace Drupal\sti_dedupe\Claims;

/**
 * One claimed value, identified the way a curator's verdict refers to it.
 *
 * Identity is collection + property term + value key, never the claim
 * paragraph id: ird_claims deletes and recreates a collection's claim
 * paragraphs on every re-import, so a verdict pinned to a paragraph id would
 * be orphaned by the next import even when nothing changed.
 */
final class ClaimTuple {

  /**
   * Constructs a ClaimTuple.
   *
   * @param int $collectionNid
   *   The collection that made the claim.
   * @param int $propertyTid
   *   The collection's own claim_properties term.
   * @param string $atioProperty
   *   The ATIO property URI that term is mapped to.
   * @param string $valueKey
   *   Comparable value: "uri:{exactMatch}" for a mapped value term,
   *   otherwise "label:{normalised label}" for a term or a literal.
   * @param string $valueLabel
   *   The value as the collection wrote it, for display.
   */
  public function __construct(
    public readonly int $collectionNid,
    public readonly int $propertyTid,
    public readonly string $atioProperty,
    public readonly string $valueKey,
    public readonly string $valueLabel,
  ) {}

  /**
   * The key a verdict is stored under.
   */
  public function key(): string {
    return self::buildKey($this->collectionNid, $this->propertyTid, $this->valueKey);
  }

  /**
   * Builds a verdict key from its parts.
   */
  public static function buildKey(int $collectionNid, int $propertyTid, string $valueKey): string {
    return $collectionNid . '|' . $propertyTid . '|' . $valueKey;
  }

}
