<?php

declare(strict_types=1);

namespace Drupal\sti_rdf\Plugin\rdf_sync\RdfUriGenerator;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\rdf_sync\Attribute\RdfUriGenerator;
use Drupal\rdf_sync\RdfUriGeneratorPluginBase;
use Drupal\sti_rdf\Plugin\Derivative\StiUriTypeDeriver;
use Drupal\sti_rdf\StiUri;

/**
 * Mints https://sti-portal.fao.org/id/{type}/{uuid} instance URIs.
 *
 * Always used through a derivative naming the type, e.g.
 * 'sti_namespace:source-record'. See StiUri for why the type is a fixed slug
 * and not the bundle machine name.
 */
#[RdfUriGenerator(
  id: 'sti_namespace',
  label: new TranslatableMarkup('STI namespace'),
  deriver: StiUriTypeDeriver::class,
)]
final class StiRdfUriGenerator extends RdfUriGeneratorPluginBase {

  /**
   * {@inheritdoc}
   */
  public function generate(): string {
    $type = $this->getDerivativeId();
    if ($type === NULL) {
      throw new \LogicException("The sti_namespace URI generator must be configured with a type derivative, e.g. 'sti_namespace:source-record'.");
    }
    return StiUri::forEntity($type, (string) $this->getEntity()->uuid());
  }

}
