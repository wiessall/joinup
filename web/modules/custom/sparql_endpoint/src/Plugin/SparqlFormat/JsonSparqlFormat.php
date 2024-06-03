<?php

declare(strict_types=1);

namespace Drupal\sparql_endpoint\Plugin\SparqlFormat;

/**
 * Provides a 'JSON' SPARQL format.
 *
 * @SparqlFormat(
 *   id = "sparql-results+json",
 *   weight = 5,
 *   mimeType = "application/sparql-results+json",
 *   label = @Translation("JSON"),
 * )
 */
class JsonSparqlFormat extends BaseSparqlFormat {

}
