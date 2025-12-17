<?php

declare(strict_types=1);

namespace Drupal\joinup_rdf\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Provides derivatives for JoinupRdfUriGenerator.
 */
class JoinupPoNamespace extends DeriverBase {

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition): array {
    $this->derivatives = [];
    $namespaces = [
      'w21' => 'Joinup PO namespace (data.europa.eu/w21)',
      's2k' => 'Joinup PO namespace (data.europa.eu/s2k)',
    ];

    foreach ($namespaces as $namespace) {
      $this->derivatives[$namespace] = [
        'label' => new TranslatableMarkup("Joinup PO namespace (data.europa.eu/$namespace)"),
      ] + $base_plugin_definition;
    }

    return $this->derivatives;
  }

}
