<?php

declare(strict_types=1);

namespace Drupal\sparql_endpoint\Plugin\SparqlFormat;

/**
 * Provides a 'CSV' SPARQL format.
 *
 * @SparqlFormat(
 *   id = "text/csv",
 *   weight = 10,
 *   label = @Translation("CSV"),
 * )
 */
class CsvSparqlFormat extends BaseSparqlFormat {

  /**
   * {@inheritdoc}
   */
  protected function getHeaders(): array {
    $headers = parent::getHeaders();
    $headers['Content-Disposition'] = 'attachment; filename="sparql-results.csv"';

    return $headers;
  }

}
