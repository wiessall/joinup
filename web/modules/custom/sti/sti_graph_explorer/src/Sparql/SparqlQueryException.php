<?php

declare(strict_types=1);

namespace Drupal\sti_graph_explorer\Sparql;

/**
 * The triplestore could not be reached or rejected a query.
 */
final class SparqlQueryException extends \RuntimeException {}
