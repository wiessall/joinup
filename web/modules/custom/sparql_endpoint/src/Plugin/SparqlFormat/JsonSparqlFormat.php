<?php

declare(strict_types=1);

namespace Drupal\sparql_endpoint\Plugin\SparqlFormat;

/**
 * Provides a 'JSON' SPARQL format.
 *
 * @SparqlFormat(
 *   id = "application/sparql-results+json",
 *   weight = 5,
 *   label = @Translation("JSON"),
 * )
 */
class JsonSparqlFormat extends BaseSparqlFormat {

}
