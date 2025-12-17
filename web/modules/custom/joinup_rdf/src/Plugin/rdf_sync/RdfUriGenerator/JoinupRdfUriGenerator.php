<?php

declare(strict_types=1);

namespace Drupal\joinup_rdf\Plugin\rdf_sync\RdfUriGenerator;

use Drupal\rdf_sync\RdfUriGeneratorPluginBase;

/**
 * Generates RDF URIs within the namespace assigned by the publication office.
 */
/**
 * @RdfUriGenerator(
 *   id = "joinup_po_namespace",
 *   label = @Translation("Joinup PO namespace"),
 *   deriver = "Drupal\joinup_rdf\Plugin\Derivative\JoinupPoNamespace"
 * )
 */
class JoinupRdfUriGenerator extends RdfUriGeneratorPluginBase {

  /**
   * {@inheritdoc}
   */
  public function generate(): string {
    $namespace = $this->getDerivativeId();

    return "http://data.europa.eu/{$namespace}/" . $this->getEntity()->uuid();
  }

}
