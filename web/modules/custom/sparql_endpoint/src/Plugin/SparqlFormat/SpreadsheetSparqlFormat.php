<?php

declare(strict_types=1);

namespace Drupal\sparql_endpoint\Plugin\SparqlFormat;

/**
 * Provides a 'Spreadsheet' SPARQL format.
 *
 * @SparqlFormat(
 *   id = "application/vnd.ms-excel",
 *   weight = 3,
 *   label = @Translation("Spreadsheet"),
 * )
 */
class SpreadsheetSparqlFormat extends BaseSparqlFormat {

}
