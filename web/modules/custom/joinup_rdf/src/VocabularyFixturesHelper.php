<?php

declare(strict_types = 1);

namespace Drupal\joinup_rdf;

use Drupal\sparql_entity_storage\Driver\Database\sparql\ConnectionInterface;
use EasyRdf\Graph;
use EasyRdf\GraphStore;

/**
 * Contains helper methods to handle and import vocabularies from fixtures.
 */
class VocabularyFixturesHelper implements VocabularyFixturesHelperInterface {

  /**
   * The SPARQL connection service.
   *
   * @var \Drupal\sparql_entity_storage\Driver\Database\sparql\ConnectionInterface
   */
  protected $connection;

  /**
   * Constructs a VocabularyFixturesHelper.
   *
   * @param \Drupal\sparql_entity_storage\Driver\Database\sparql\ConnectionInterface $connection
   *   The SPARQL connection service.
   */
  public function __construct(ConnectionInterface $connection) {
    $this->connection = $connection;
  }

  /**
   * Returns an array of allowed values and data related to the imports.
   *
   * @return array
   *   An array of allowed values where each entry is a an array containing the
   *   following data:
   *   - graph: The graph uri of the vocabulary.
   *   - filename: The filename of the vocabulary.
   *   - extra queries: Extra queries that should run after each import of the
   *   file.
   */
  protected function getFixturesData(): array {
    return [
      'licence-legal-type' => [
        'graph' => 'http://licence-legal-type',
        'filename' => 'licence-legal-type.rdf',
        'extra queries' => [
          'WITH <http://licence-legal-type> INSERT { ?subject a skos:Concept } WHERE { ?subject a skos:Collection . };',
          'WITH <http://licence-legal-type> INSERT { ?subject skos:topConceptOf <http://joinup.eu/legal-type#> } WHERE { ?subject a skos:Concept . FILTER NOT EXISTS { ?subject skos:topConceptOf <http://joinup.eu/legal-type#> } };',
          'WITH <http://licence-legal-type> INSERT { ?member skos:broaderTransitive ?collection } WHERE { ?collection a skos:Collection . ?collection skos:member ?member };',
        ],
      ],
      'eif_solution_type' => [
        'graph' => 'http://eif_solution_type',
        'filename' => 'eif_solution_type.rdf',
        'extra queries' => [],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function clearFixturesGraph(string $graph_uri): void {
    $this->connection->getSparqlClient()->clear($graph_uri);
  }

  /**
   * {@inheritdoc}
   */
  public function importFixtures(string $fixture_key, bool $clear_graph = TRUE): void {
    $fixtures_data = $this->getFixturesData();
    if (!isset($fixtures_data[$fixture_key])) {
      throw new \Exception("Invalid option '{$fixture_key}' supply'.");
    }

    $filename = DRUPAL_ROOT . '/../resources/fixtures/' . $fixtures_data[$fixture_key]['filename'];
    $this->importFixtureToGraph(
      $filename,
      $fixtures_data[$fixture_key]['graph'],
      $clear_graph,
      $fixtures_data[$fixture_key]['extra queries']
    );
  }

  /**
   * {@inheritdoc}
   */
  public function importFixtureToGraph(string $filename, string $graph_uri, bool $clear_graph = TRUE, array $extra_queries = []): void {
    if ($clear_graph) {
      $this->clearFixturesGraph($graph_uri);
    }

    $graph = new Graph($graph_uri);
    $graph->parseFile($filename);

    $graph_store = $this->getDefaultGraphStore();
    $graph_store->insert($graph);

    foreach ($extra_queries as $query) {
      $this->connection->query($query);
    }
  }

  /**
   * Returns a graph store with the default connection options.
   *
   * @return \EasyRdf\GraphStore
   *   The graph store object.
   */
  protected function getDefaultGraphStore(): GraphStore {
    $connection_options = $this->connection->getConnectionOptions();
    $connect_string = "http://{$connection_options['host']}:{$connection_options['port']}/sparql-graph-crud";
    return new GraphStore($connect_string);
  }

}
