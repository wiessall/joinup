<?php

declare(strict_types=1);

namespace Drupal\sparql_endpoint\Plugin\SparqlFormat;

/**
 * Provides a 'Spreadsheet' SPARQL format.
 *
 * @SparqlFormat(
 *   id = "vnd.ms-excel",
 *   weight = 3,
 *   mimeType = "application/vnd.ms-excel",
 *   label = @Translation("Spreadsheet"),
 * )
 */
class SpreadsheetSparqlFormat extends BaseSparqlFormat {

}
