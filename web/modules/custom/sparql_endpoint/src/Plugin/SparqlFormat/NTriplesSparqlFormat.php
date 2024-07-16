<?php

declare(strict_types=1);

namespace Drupal\sparql_endpoint\Plugin\SparqlFormat;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\sparql_endpoint\Plugin\Attribute\SparqlFormat;

/**
 * Provides a 'N-Triples' SPARQL format.
 */
#[SparqlFormat(
  id: 'text/ntriples',
  label: new TranslatableMarkup('N-Triples'),
  weight: 9,
)]
class NTriplesSparqlFormat extends BaseSparqlFormat {

}
