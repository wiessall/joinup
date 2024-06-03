<?php

declare(strict_types=1);

namespace Drupal\sparql_endpoint\Plugin\SparqlFormat;

/**
 * Provides a 'HTML' SPARQL format.
 *
 * @SparqlFormat(
 *   id = "html",
 *   weight = 1,
 *   mimeType = "text/html",
 *   label = @Translation("HTML"),
 * )
 */
class HtmlSparqlFormat extends BaseSparqlFormat {

}
