<?php

declare(strict_types=1);

namespace Drupal\joinup_rdf\Plugin\rdf_sync\RdfUriGenerator;

use Drupal\rdf_sync\RdfUriGeneratorPluginBase;

/**
 * Generates RDF URIs within the namespace assigned by the publication office.
 *
 * @RdfUriGenerator(
 *   id = "joinup_po_namespace",
 *   name = @Translation("Joinup PO namespace (data.europa.eu/w21)"),
 * )
 */
class JoinupRdfUriGenerator extends RdfUriGeneratorPluginBase {

  /**
   * {@inheritdoc}
   */
  public function generate(): string {
    return 'http://data.europa.eu/w21/' . $this->getEntity()->uuid();
  }

}
