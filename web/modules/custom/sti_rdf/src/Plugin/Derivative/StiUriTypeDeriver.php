<?php

declare(strict_types=1);

namespace Drupal\sti_rdf\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\sti_rdf\StiUri;

/**
 * One sti_namespace derivative per instance type, e.g. sti_namespace:claim.
 *
 * The derivative id is the slug that appears in the URI, so a bundle's URIs
 * are fixed by its config (uri_plugin: 'sti_namespace:source-record') and do
 * not depend on its machine name.
 */
final class StiUriTypeDeriver extends DeriverBase {

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition): array {
    $this->derivatives = [];
    foreach (StiUri::TYPES as $type => $label) {
      $this->derivatives[$type] = [
        'label' => new TranslatableMarkup('STI namespace: @label (@uri)', [
          '@label' => $label,
          '@uri' => StiUri::ID_BASE . $type . '/…',
        ]),
      ] + $base_plugin_definition;
    }
    return $this->derivatives;
  }

}
