<?php

declare(strict_types=1);

namespace Drupal\sti_rdf\Plugin\rdf_sync\RdfUriGenerator;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\rdf_sync\Attribute\RdfUriGenerator;
use Drupal\rdf_sync\RdfUriGeneratorPluginBase;

/**
 * Generates RDF URIs within the namespace assigned by the publication office.
 */
#[RdfUriGenerator(
  id: "sti_namespace",
  label: new TranslatableMarkup("STI namespace"),
  // deriver: "Drupal\joinup_rdf\Plugin\Derivative\JoinupPoNamespace"
)]
class StiRdfUriGenerator extends RdfUriGeneratorPluginBase {

  /**
   * {@inheritdoc}
   */
  public function generate(): string {
    $namespace = $this->getDerivativeId() ?? $this->getEntity()->bundle();
    return "http://sti-portal.fao.org/{$namespace}/" . $this->getEntity()->uuid();
  }

}