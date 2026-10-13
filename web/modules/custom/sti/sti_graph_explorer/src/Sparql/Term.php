<?php

declare(strict_types=1);

namespace Drupal\sti_graph_explorer\Sparql;

/**
 * One RDF term from a SPARQL JSON result binding.
 */
final class Term {

  public const IRI = 'uri';
  public const LITERAL = 'literal';
  public const BNODE = 'bnode';

  public function __construct(
    public readonly string $type,
    public readonly string $value,
    public readonly string $lang = '',
    public readonly string $datatype = '',
  ) {}

  /**
   * Whether this term is an IRI.
   */
  public function isIri(): bool {
    return $this->type === self::IRI;
  }

  /**
   * Whether this term is a literal.
   */
  public function isLiteral(): bool {
    return $this->type === self::LITERAL;
  }

  /**
   * Returns the lexical value as an integer (for COUNT results).
   */
  public function toInt(): int {
    return (int) $this->value;
  }

}
