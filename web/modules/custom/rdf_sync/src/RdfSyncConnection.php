<?php

declare(strict_types=1);

namespace Drupal\rdf_sync;

use EasyRdf\Graph;
use EasyRdf\GraphStore;
use EasyRdf\Http\Exception as EasyRdfException;
use EasyRdf\Serialiser\Ntriples;
use EasyRdf\Sparql\Client;
use EasyRdf\Sparql\Result;

/**
 * Helper class to allow connection to the RDF backend.
 */
class RdfSyncConnection {

  /**
   * Used graph for all triples.
   *
   * @const string
   */
  public const GRAPH = 'http://data';

  /**
   * Static cache for EasyRdf client.
   */
  protected static Client $client;

  /**
   * {@inheritdoc}
   */
  public static function createGraphStore(): GraphStore {
    $connectionString = static::getEndpointUrl() . '/sparql-graph-crud';
    return new GraphStore($connectionString);
  }

  /**
   * Runs a SPARQL query and returns the results.
   *
   * @param string $query
   *   The SPARQL query.
   *
   * @return \EasyRdf\Sparql\Result
   *   The result object.
   */
  public static function query(string $query): Result {
    return static::performOperation($query, 'query');
  }

  /**
   * Runs a SPARQL update and returns the results.
   *
   * @param string $query
   *   The SPARQL query.
   *
   * @return \EasyRdf\Sparql\Result
   *   The result object.
   */
  public static function update(string $query): Result {
    return static::performOperation($query, 'update');
  }

  /**
   * Deletes a list of triples from the SPARQL backend given their subjects.
   *
   * @param array $uris
   *   A list of triple URIs to be deleted.
   *
   * @todo Consider using graph store operations.
   */
  public static function deleteTriples(array $uris): void {
    $serializer = new Ntriples();
    $uris = array_map(
      function (string $uri) use ($serializer): string {
        return $serializer->serialiseValue(['value' => $uri, 'type' => 'uri']);
      },
      $uris,
    );

    $graphUri = self::GRAPH;
    $uris = implode(",\n", $uris);
    $query = <<<QUERY
        WITH <$graphUri>
        DELETE  { ?subject ?predicate ?object . }
        WHERE {
        ?subject ?predicate ?object .
        FILTER(?subject IN (
        $uris
        )) .
        }
        QUERY;
    self::update($query);
  }

  /**
   * Inserts a list of triples in the SPARQL triple-store.
   *
   * @param array $triples
   *   An array of triples structured as the return of Graph::toRdfPhp().
   *
   * @see \EasyRdf\Graph::toRdfPhp
   */
  public static function insertTriples(array $triples): void {
    $graph = new Graph();
    $graph->parse($triples, 'php');
    $triples = $graph->serialise('ntriples');
    $graphUri = self::GRAPH;
    $query = <<<QUERY
      WITH <$graphUri>
      INSERT {
      $triples
      }
      QUERY;
    self::update($query);
  }

  /**
   * Updates a list of triples in the SPARQL triple-store.
   *
   * @param array $triples
   *   An array of triples structured as the return of Graph::toRdfPhp().
   *
   * @see \EasyRdf\Graph::toRdfPhp
   */
  public static function updateTriples(array $triples): void {
    static::deleteTriples(array_keys($triples));
    static::insertTriples($triples);
  }

  /**
   * Provides a helper to run a query or an update.
   *
   * @param string $query
   *   The SPARQL query.
   * @param string $method
   *   The method: 'query' or 'update'.
   *
   * @return \EasyRdf\Sparql\Result
   *   The result object.
   */
  protected static function performOperation(string $query, string $method): Result {
    assert(in_array($method, ['query', 'update'], TRUE));

    try {
      return static::getClient()->$method($query);
    }
    catch (EasyRdfException $exception) {
      // Re-throw the exception, but with the query as message.
      throw new \Exception("Execution of $method failed with message '{$exception->getBody()}'. Query: " . $query, $exception->getCode(), $exception);
    }
  }

  /**
   * Returns the Easy RDF SPARQL client.
   *
   * @return \EasyRdf\Sparql\Client
   *   The Easy RDF SPARQL client.
   */
  protected static function getClient(): Client {
    if (!isset(static::$client)) {
      static::$client = new Client(static::getEndpointUrl() . '/sparql');
    }
    return static::$client;
  }

  /**
   * Builds the SPARQL endpoint URL.
   *
   * @return string
   *   The SPARQL endpoint URL.
   */
  protected static function getEndpointUrl(): string {
    $scheme = getenv('DRUPAL_SPARQL_SCHEME') ?: 'http';
    $host = getenv('DRUPAL_SPARQL_HOSTNAME');
    $port = getenv('DRUPAL_SPARQL_PORT') ?: '8890';
    return "$scheme://$host:$port";
  }

}
