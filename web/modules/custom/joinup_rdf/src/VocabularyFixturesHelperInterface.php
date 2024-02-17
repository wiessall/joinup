<?php

declare(strict_types=1);

namespace Drupal\joinup_rdf;

use EasyRdf\Http\Response;
use EasyRdf\Sparql\Result;

/**
 * Interface for services that provide fixtures import related helper methods.
 */
interface VocabularyFixturesHelperInterface {

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
  public function getFixturesData(): array;

  /**
   * Clears the given graph URI.
   *
   * @param string $graph_uri
   *   The graph URI to clear.
   */
  public function clearFixturesGraph(string $graph_uri): Response|Result;

  /**
   * Imports a single file fixture into the SPARQL endpoint.
   *
   * @param string $fixture_key
   *   A key identifying the fixture to import. The key is mainly the filename
   *   without the extension.
   * @param bool $clear_graph
   *   Whether to clear the graph before importing. Defaults to TRUE.
   *
   * @return array
   *   An array of log messages.
   */
  public function importFixtures(string $fixture_key, bool $clear_graph = TRUE): array;

  /**
   * Imports a single file into the SPARQL endpoint.
   *
   * @param string $filename
   *   The filename from where to load the data.
   * @param string $graph_uri
   *   The graph URI where to import the data.
   * @param bool $clear_graph
   *   Whether to clear the graph before importing. Defaults to TRUE.
   * @param array $extra_queries
   *   An array of extra queries to run after the import.
   *
   * @return array
   *   An array of log messages.
   */
  public function importFixtureToGraph(string $filename, string $graph_uri, bool $clear_graph = TRUE, array $extra_queries = []): array;

}
