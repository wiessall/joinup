<?php

declare(strict_types=1);

namespace Drupal\sparql_endpoint\Plugin\SparqlFormat;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\sparql_endpoint\Plugin\Attribute\SparqlFormat;

/**
 * Provides a 'RDF/XML' SPARQL format.
 */
#[SparqlFormat(
  id: 'application/rdf+xml',
  label: new TranslatableMarkup('RDF/XML'),
  weight: 8,
)]
class RdfXmlSparqlFormat extends BaseSparqlFormat {

}
