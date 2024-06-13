<?php

declare(strict_types=1);

namespace Drupal\sparql_endpoint\Plugin\SparqlFormat;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\sparql_endpoint\Plugin\Attribute\SparqlFormat;

/**
 * Provides a 'Javascript' SPARQL format.
 */
#[SparqlFormat(
  id: 'application/javascript',
  label: new TranslatableMarkup('Javascript'),
  weight: 6,
)]
class JavascriptSparqlFormat extends BaseSparqlFormat {

}
