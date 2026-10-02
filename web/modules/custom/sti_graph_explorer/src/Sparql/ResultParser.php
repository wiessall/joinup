<?php

declare(strict_types=1);

namespace Drupal\sti_graph_explorer\Sparql;

use Psr\Http\Message\ResponseInterface;

/**
 * Turns a SPARQL 1.1 JSON results response into a SelectResult.
 *
 * Truncation is the case that matters. When a query runs past
 * MaxQueryExecutionTime, Virtuoso does not fail: it answers 200 with whatever
 * it has so far and says so only in the X-SQL-State / X-SQL-Message headers
 * ("anytime query"). A caller that only reads the body would present partial
 * counts as the truth, so this parser reads the headers and marks the result.
 */
final class ResultParser {

  /**
   * Parses a response to a SELECT query.
   *
   * @throws \Drupal\sti_graph_explorer\Sparql\SparqlQueryException
   *   If the response is not a successful SPARQL JSON result.
   */
  public function parse(ResponseInterface $response): SelectResult {
    $status = $response->getStatusCode();
    $body = (string) $response->getBody();
    if ($status < 200 || $status >= 300) {
      throw new SparqlQueryException(sprintf('The triplestore answered HTTP %d: %s', $status, mb_substr(trim($body), 0, 300)));
    }

    try {
      $data = json_decode($body, TRUE, 64, JSON_THROW_ON_ERROR);
    }
    catch (\JsonException $e) {
      throw new SparqlQueryException('The triplestore did not return SPARQL JSON results.', 0, $e);
    }
    if (!is_array($data) || !isset($data['results']['bindings']) || !is_array($data['results']['bindings'])) {
      throw new SparqlQueryException('The triplestore response has no results.bindings.');
    }

    $rows = [];
    foreach ($data['results']['bindings'] as $binding) {
      $row = [];
      foreach ($binding as $variable => $term) {
        $row[(string) $variable] = $this->term($term);
      }
      $rows[] = $row;
    }

    $state = $response->getHeaderLine('X-SQL-State');
    return new SelectResult(
      $rows,
      $state !== '',
      $state === '' ? '' : trim($state . ' ' . $response->getHeaderLine('X-SQL-Message')),
    );
  }

  /**
   * Builds a Term from one JSON binding value.
   *
   * Virtuoso still emits the pre-standard "typed-literal" type; it is a
   * literal with a datatype.
   */
  private function term(array $term): Term {
    $type = (string) ($term['type'] ?? '');
    return match ($type) {
      'uri' => new Term(Term::IRI, (string) ($term['value'] ?? '')),
      'bnode' => new Term(Term::BNODE, (string) ($term['value'] ?? '')),
      'literal', 'typed-literal' => new Term(
        Term::LITERAL,
        (string) ($term['value'] ?? ''),
        (string) ($term['xml:lang'] ?? ''),
        (string) ($term['datatype'] ?? ''),
      ),
      default => throw new SparqlQueryException(sprintf('Unknown RDF term type "%s" in results.', $type)),
    };
  }

}
