<?php

declare(strict_types=1);

namespace Drupal\sparql_endpoint\Plugin\SparqlFormat;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\sparql_endpoint\Plugin\Attribute\SparqlFormat;

/**
 * Provides a 'Spreadsheet' SPARQL format.
 */
#[SparqlFormat(
  id: 'application/vnd.ms-excel',
  label: new TranslatableMarkup('Spreadsheet'),
  weight: 3,
)]
class SpreadsheetSparqlFormat extends BaseSparqlFormat {

}
