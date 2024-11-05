<?php

declare(strict_types=1);

namespace Drupal\joinup_rdf;

use Drupal\rdf_sync\RdfSyncConnectionInterface;
use EasyRdf\Graph;
use EasyRdf\GraphStore;
use EasyRdf\Http\Response;
use EasyRdf\Sparql\Result;

/**
 * Contains helper methods to handle and import vocabularies from fixtures.
 */
class VocabularyFixturesHelper implements VocabularyFixturesHelperInterface {

  /**
   * Constructs a VocabularyFixturesHelper.
   *
   * @param \Drupal\rdf_sync\RdfSyncConnectionInterface $connection
   *   The SPARQL connection service.
   */
  public function __construct(
    protected RdfSyncConnectionInterface $connection,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getFixturesData(): array {
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
      'eira_skos' => [
        'graph' => 'http://eira_skos',
        'filename' => 'EIRA_SKOS.rdf',
        'extra queries' => [
          'DELETE { GRAPH <http://eira_skos> { ?subject ?predicate ?object } } INSERT { GRAPH <http://eira_skos_properties> { ?subject ?predicate ?object } } WHERE { GRAPH <http://eira_skos> { ?subject ?predicate ?object . FILTER (regex(str(?subject), "_Properties$")) } }',
          'DELETE {GRAPH <http://eira_skos> { ?subject ?predicate ?object } } INSERT { GRAPH <http://eira_skos_properties> { ?subject ?predicate ?object } } WHERE { GRAPH <http://eira_skos> { ?subject ?predicate ?object . FILTER (regex(str(?object), "_Properties$")) } }',
          'WITH <http://eira_skos_properties> DELETE { ?subject skos:topConceptOf <http://data.europa.eu/dr8> } WHERE { ?subject a skos:Concept . }',
          'WITH <http://eira_skos> INSERT { ?subject a skos:Concept } WHERE { ?subject a skos:Collection . }',
          'WITH <http://eira_skos> INSERT { ?subject skos:topConceptOf <http://data.europa.eu/dr8> } WHERE { ?subject a skos:Concept .}',
          'WITH <http://eira_skos> INSERT { ?member skos:broaderTransitive ?collection } WHERE { ?collection a skos:Collection . ?collection skos:member ?member }',
          'WITH <http://countries-skos> DELETE { ?entity ?field ?value. } WHERE { ?entity ?field ?value . ?entity <http://publications.europa.eu/ontology/authority/end.use> ?date . FILTER ( bound(?date) ) }',
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function clearFixturesGraph(string $graph_uri): Response|Result {
    return $this->connection->update("CLEAR GRAPH <$graph_uri>;");
  }

  /**
   * {@inheritdoc}
   */
  public function importFixtures(string $fixture_key, bool $clear_graph = TRUE): array {
    $fixtures_data = $this->getFixturesData();
    if (!isset($fixtures_data[$fixture_key])) {
      throw new \Exception("Invalid option '{$fixture_key}' supply'.");
    }

    $filename = DRUPAL_ROOT . '/../resources/fixtures/' . $fixtures_data[$fixture_key]['filename'];
    return $this->importFixtureToGraph(
      $filename,
      $fixtures_data[$fixture_key]['graph'],
      $clear_graph,
      $fixtures_data[$fixture_key]['extra queries']
    );
  }

  /**
   * {@inheritdoc}
   */
  public function importFixtureToGraph(string $filename, string $graph_uri, bool $clear_graph = TRUE, array $extra_queries = []): array {
    $messages = [];
    $messages[] = "======== File path: {$filename} ========\n";
    $messages[] = "File exists: " . (file_exists($filename) ? 'yes' : 'no') . "\n";
    $messages[] = "File readable: " . (is_readable($filename) ? 'yes' : 'no') . "\n";
    $messages[] = "File size: " . filesize($filename) . " bytes.\n";
    $messages[] = "======== End file path ========\n";
    if ($clear_graph) {
      $results = $this->clearFixturesGraph($graph_uri);
      $messages[] = $results->dump() . "\n";
    }

    $graph = new Graph($graph_uri);
    $triples_added = $graph->parseFile($filename);
    $messages[] = "======== Adding triples to the graph ========\n";
    $messages[] = "Added {$triples_added} triples to graph store object {$graph_uri}.\n";
    $messages[] = "======== End adding triples to the graph ========\n";

    $graph_store = $this->getDefaultGraphStore();
    $response = $graph_store->insert($graph);
    $messages[] = "======== Inserting graph into the store ========\n";
    $messages[] = "Response: {$response->asString()}\n";
    $messages[] = "======== End inserting graph into the store ========\n";

    foreach ($extra_queries as $query) {
      $results = $this->connection->query($query);
      $messages[] = "======== Running extra query ========\n";
      $messages[] = "Query: {$query}\n";
      $messages[] = $results->dump() . "\n";
      $messages[] = "======== End running extra query ========\n";
    }

    return $messages;
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
