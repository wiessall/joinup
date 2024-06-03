<?php

declare(strict_types=1);

namespace Drupal\sparql_endpoint\Plugin\SparqlFormat;

/**
 * Provides a 'TSV' SPARQL format.
 *
 * @SparqlFormat(
 *   id = "text/tab-separated-values",
 *   weight = 11,
 *   label = @Translation("TSV"),
 * )
 */
class TsvSparqlFormat extends BaseSparqlFormat {

  /**
   * {@inheritdoc}
   */
  protected function getHeaders(): array {
    $headers = parent::getHeaders();
    $headers['Content-Disposition'] = 'attachment; filename="sparql-results.tsv"';

    return $headers;
  }

}
