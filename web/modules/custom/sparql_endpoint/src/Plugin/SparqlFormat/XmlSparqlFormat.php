<?php

declare(strict_types=1);

namespace Drupal\sparql_endpoint\Plugin\SparqlFormat;

/**
 * Provides a 'XML' SPARQL format.
 *
 * @SparqlFormat(
 *   id = "sparql-results+xml",
 *   weight = 4,
 *   mimeType = "application/sparql-results+xml",
 *   label = @Translation("XML"),
 * )
 */
class XmlSparqlFormat extends BaseSparqlFormat {

  /**
   * {@inheritdoc}
   */
  protected function getHeaders(): array {
    $headers = parent::getHeaders();
    $headers['Content-Disposition'] = 'attachment; filename="sparql-results.xml"';

    return $headers;
  }

}
