<?php

declare(strict_types=1);

namespace Drupal\sparql_endpoint\Plugin\SparqlFormat;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\sparql_endpoint\Plugin\Attribute\SparqlFormat;

/**
 * Provides a 'CSV' SPARQL format.
 */
#[SparqlFormat(
  id: 'text/csv',
  label: new TranslatableMarkup('CSV'),
  weight: 10,
)]
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
