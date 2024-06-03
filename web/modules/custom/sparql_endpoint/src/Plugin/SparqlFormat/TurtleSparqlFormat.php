<?php

declare(strict_types=1);

namespace Drupal\sparql_endpoint\Plugin\SparqlFormat;

/**
 * Provides a 'HTML' SPARQL format.
 *
 * @SparqlFormat(
 *   id = "turtle",
 *   weight = 7,
 *   mimeType = "text/turtle",
 *   label = @Translation("Turtle"),
 * )
 */
class TurtleSparqlFormat extends BaseSparqlFormat {

}
