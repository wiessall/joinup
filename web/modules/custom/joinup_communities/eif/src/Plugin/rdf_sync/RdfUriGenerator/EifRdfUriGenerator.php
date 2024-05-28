<?php

declare(strict_types=1);

namespace Drupal\eif\Plugin\rdf_sync\RdfUriGenerator;

use Drupal\rdf_sync\RdfUriGeneratorPluginBase;

/**
 * Generates RDF URIs for EIF perspective class.
 *
 * @RdfUriGenerator(
 *   id = "eif_perspective",
 *   name = @Translation("EIF Perspective"),
 * )
 */
class EifRdfUriGenerator extends RdfUriGeneratorPluginBase {

  /**
   * {@inheritdoc}
   */
  public function generate(): string {
    return "http://data.europa.eu/eif/Perspective/{$this->getEntity()->uuid()}";
  }

}
