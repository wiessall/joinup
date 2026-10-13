<?php

declare(strict_types=1);

namespace Drupal\sti_graph_explorer\Sparql;

/**
 * Runs read-only SELECT queries.
 */
interface SparqlClientInterface {

  /**
   * Runs a SELECT query.
   *
   * @throws \Drupal\sti_graph_explorer\Sparql\SparqlQueryException
   *   If the store is unreachable or rejects the query.
   */
  public function select(string $query): SelectResult;

}
