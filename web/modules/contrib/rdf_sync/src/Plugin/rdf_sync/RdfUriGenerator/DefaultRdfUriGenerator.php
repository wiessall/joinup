<?php

declare(strict_types=1);

namespace Drupal\rdf_sync\Plugin\rdf_sync\RdfUriGenerator;

use Drupal\rdf_sync\RdfUriGeneratorPluginBase;

/**
 * Provides a fallback entity ID generator plugin.
 *
 * @RdfUriGenerator(
 * id = "default",
 * name = @Translation("Default URI generator"),
 * )
 */
class DefaultRdfUriGenerator extends RdfUriGeneratorPluginBase {

  /**
   * {@inheritdoc}
   */
  public function generate(): string {
    $uri = "{$GLOBALS['base_url']}/{$this->getEntity()->getEntityTypeId()}/{$this->getEntity()->bundle()}/{$this->getEntity()->uuid()}";
    return str_replace('_', '-', $uri);
  }

}
