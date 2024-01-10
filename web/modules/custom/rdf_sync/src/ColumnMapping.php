<?php

declare(strict_types = 1);

namespace Drupal\rdf_sync;

/**
 * Data model for a mapping.
 */
class ColumnMapping {

  /**
   * Constructs a new column mapper instance.
   *
   * @param string $predicate
   *   The mapping predicate.
   * @param string $type
   *   The mapping type.
   */
  public function __construct(
    public readonly string $predicate,
    public readonly string $type,
  ) {}

  /**
   * Creates a mapping object given an array.
   *
   * @param array $mapping
   *   The mapping array, having two keys: 'predicate' and 'type'.
   *
   * @return $this
   */
  public static function createFromArray(array $mapping): self {
    if (!isset($mapping['predicate'])) {
      throw new \InvalidArgumentException("Missing the 'predicate' key");
    }
    if (!isset($mapping['type'])) {
      throw new \InvalidArgumentException("Missing the 'type' key");
    }
    return new static($mapping['predicate'], $mapping['type']);
  }

}
