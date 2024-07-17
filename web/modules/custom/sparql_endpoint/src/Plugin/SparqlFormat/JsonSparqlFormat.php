<?php

declare(strict_types=1);

namespace Drupal\sparql_endpoint\Plugin\SparqlFormat;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\sparql_endpoint\Plugin\Attribute\SparqlFormat;

/**
 * Provides a 'JSON' SPARQL format.
 */
#[SparqlFormat(
  id: 'application/sparql-results+json',
  label: new TranslatableMarkup('JSON'),
  weight: 5,
)]
class JsonSparqlFormat extends BaseSparqlFormat {

}
