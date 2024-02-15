<?php

declare(strict_types=1);

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
   * @param string|null $type
   *   The RDF type which could be 'resource', one of 'xsd:*' types or NULL.
   *   NULL is used for non-types, i.e., for translatable fields.
   */
  public function __construct(
    public string $predicate,
    public ?string $type,
  ) {}

  /**
   * Creates a mapping object given an array.
   *
   * @param array $mapping
   *   The mapping array has two keys:
   *   - predicate: The RDF predicate to map to.
   *   - type: The RDF type which could be 'resource', one of 'xsd:*' types or
   *     NULL. NULL is used for non-types, i.e., for translatable fields.
   *
   * @return $this
   */
  public static function createFromArray(array $mapping): self {
    return new static($mapping['predicate'], $mapping['type'] ?? NULL);
  }

}
