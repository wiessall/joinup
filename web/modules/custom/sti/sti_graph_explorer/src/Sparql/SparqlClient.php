<?php

declare(strict_types=1);

namespace Drupal\sti_graph_explorer\Sparql;

use Drupal\sparql_endpoint\SparqlReadOnlyEndpointInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;

/**
 * SELECT queries through Joinup's read-only endpoint (Virtuoso SPARQL_RO).
 */
final class SparqlClient implements SparqlClientInterface {

  public function __construct(
    private readonly SparqlReadOnlyEndpointInterface $endpoint,
    private readonly ResultParser $parser,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function select(string $query): SelectResult {
    try {
      $response = $this->endpoint->query($query, 'application/sparql-results+json');
    }
    catch (RequestException $e) {
      // Guzzle throws on 4xx/5xx; the body carries Virtuoso's error text.
      if ($e->getResponse() !== NULL) {
        return $this->parser->parse($e->getResponse());
      }
      throw new SparqlQueryException('The triplestore could not be reached: ' . $e->getMessage(), 0, $e);
    }
    catch (GuzzleException $e) {
      throw new SparqlQueryException('The triplestore could not be reached: ' . $e->getMessage(), 0, $e);
    }
    return $this->parser->parse($response);
  }

}
