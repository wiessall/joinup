<?php

declare(strict_types=1);

namespace Drupal\sparql_endpoint\Plugin\SparqlFormat;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\sparql_endpoint\Plugin\Attribute\SparqlFormat;

/**
 * Provides a 'TSV' SPARQL format.
 */
#[SparqlFormat(
  id: 'text/tab-separated-values',
  label: new TranslatableMarkup('TSV'),
  weight: 11,
)]
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
