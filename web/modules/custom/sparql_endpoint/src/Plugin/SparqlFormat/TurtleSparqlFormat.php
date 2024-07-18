<?php

declare(strict_types=1);

namespace Drupal\sparql_endpoint\Plugin\SparqlFormat;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\sparql_endpoint\Plugin\Attribute\SparqlFormat;

/**
 * Provides a 'Turtle' SPARQL format.
 */
#[SparqlFormat(
  id: 'text/turtle',
  label: new TranslatableMarkup('Turtle'),
  weight: 7,
)]
class TurtleSparqlFormat extends BaseSparqlFormat {

}
