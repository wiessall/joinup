<?php

declare(strict_types=1);

namespace Drupal\sparql_endpoint\Plugin\SparqlFormat;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\sparql_endpoint\Plugin\Attribute\SparqlFormat;

/**
 * Provides a 'HTML' SPARQL format.
 */
#[SparqlFormat(
  id: 'text/html',
  label: new TranslatableMarkup('HTML'),
  weight: 1,
)]
class HtmlSparqlFormat extends BaseSparqlFormat {

}
