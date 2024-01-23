<?php

declare(strict_types = 1);

namespace Drupal\rdf_sync\Model;

/**
 * Data model for a mapping.
 */
readonly class ColumnMapping {

  /**
   * Allowed triple object types.
   *
   * @const string[]
   */
  public const ALLOWED_TYPES = [
    'resource',
    'xsd:string',
    'xsd:boolean',
    'xsd:date',
    'xsd:dateTime',
    'xsd:decimal',
    'xsd:integer',
    'xsd:anyURI',
  ];

  /**
   * Constructs a new column mapper instance.
   *
   * @param string $predicate
   *   The mapping predicate.
   * @param string $type
   *   The mapping type.
   */
  public function __construct(
    public string $predicate,
    public string $type,
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
    return new static($mapping['predicate'], $mapping['type']);
  }

}
