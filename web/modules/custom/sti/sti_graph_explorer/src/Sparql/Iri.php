<?php

declare(strict_types=1);

namespace Drupal\sti_graph_explorer\Sparql;

/**
 * An absolute IRI that is safe to place between angle brackets in SPARQL.
 *
 * This is the only way user input becomes part of a query as an IRI. The
 * character set is the SPARQL 1.1 IRIREF production: anything that could end
 * the <...> token or start a new one (whitespace, <, >, ", {, }, |, ^, `, \)
 * is rejected rather than escaped, because SPARQL has no escape for them.
 */
final class Iri {

  private const MAX_LENGTH = 2048;

  /**
   * Scheme, colon, then only characters the IRIREF production allows.
   *
   * Anchored with \z, not $: $ also matches before a trailing newline.
   */
  private const PATTERN = '/^[A-Za-z][A-Za-z0-9+.\-]*:[^\x00-\x20<>"{}|^`\\\\]+\z/u';

  private function __construct(
    public readonly string $value,
  ) {}

  /**
   * Validates and wraps an IRI.
   *
   * @throws \InvalidArgumentException
   *   If the string is not an absolute IRI or contains characters that are
   *   not allowed inside <...> in SPARQL.
   */
  public static function fromString(string $iri): self {
    if ($iri === '' || strlen($iri) > self::MAX_LENGTH || preg_match(self::PATTERN, $iri) !== 1) {
      throw new \InvalidArgumentException('Not a usable absolute IRI.');
    }
    return new self($iri);
  }

  /**
   * Returns the IRI as a SPARQL IRIREF token.
   */
  public function toSparql(): string {
    return '<' . $this->value . '>';
  }

}
