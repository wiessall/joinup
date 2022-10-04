<?php

declare(strict_types = 1);

namespace Drupal\joinup_rdf;

/**
 * Interface for services that provide fixtures import related helper methods.
 */
interface VocabularyFixturesHelperInterface {

  /**
   * Clears the given graph URI.
   *
   * @param string $graph_uri
   *   The graph URI to clear.
   */
  public function clearFixturesGraph(string $graph_uri): void;

  /**
   * Imports a single file fixture into the SPARQL endpoint.
   *
   * @param string $fixture_key
   *   A key identifying the fixture to import. The key is mainly the filename
   *   without the extension.
   * @param bool $clear_graph
   *   Whether to clear the graph before importing. Defaults to TRUE.
   */
  public function importFixtures(string $fixture_key, bool $clear_graph = TRUE): void;

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
   */
  public function importFixtureToGraph(string $filename, string $graph_uri, bool $clear_graph = TRUE, array $extra_queries = []): void;

}
