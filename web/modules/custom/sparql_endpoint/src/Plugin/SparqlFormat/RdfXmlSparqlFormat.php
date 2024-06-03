<?php

declare(strict_types=1);

namespace Drupal\sparql_endpoint\Plugin\SparqlFormat;

/**
 * Provides a 'RDF/XML' SPARQL format.
 *
 * @SparqlFormat(
 *   id = "rdf+xml",
 *   weight = 8,
 *   mimeType = "application/rdf+xml",
 *   label = @Translation("RDF/XML"),
 * )
 */
class RdfXmlSparqlFormat extends BaseSparqlFormat {

}
