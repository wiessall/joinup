<?php

declare(strict_types=1);

namespace Drupal\sparql_endpoint\Plugin\SparqlFormat;

/**
 * Provides a 'HTML' SPARQL format.
 *
 * @SparqlFormat(
 *   id = "plain",
 *   weight = 9,
 *   mimeType = "text/plain",
 *   label = @Translation("N-Triples"),
 * )
 */
class NTriplesSparqlFormat extends BaseSparqlFormat {

}
