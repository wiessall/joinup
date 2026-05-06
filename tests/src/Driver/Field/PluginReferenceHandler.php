<?php

declare(strict_types=1);

namespace Drupal\joinup\Driver\Field;

use Drupal\Driver\Core\Field\AbstractHandler;

/**
 * Field handler for the 'plugin_reference' field type (pluginreference module).
 *
 * Storage has two columns: 'plugin_id' (the plugin manager id) and
 * 'configuration' (per-instance plugin config). Scenarios pass only the
 * plugin id as a scalar; configuration is left empty by default. A compound
 * cell with explicit 'plugin_id' / 'configuration' columns is also accepted.
 */
class PluginReferenceHandler extends AbstractHandler {

  /**
   * {@inheritdoc}
   */
  public function expand(mixed $values): array {
    $items = [];
    foreach ((array) $values as $value) {
      if (is_array($value)) {
        $plugin_id = (string) ($value['plugin_id'] ?? '');
        $configuration = $value['configuration'] ?? '';
      }
      else {
        $plugin_id = (string) $value;
        $configuration = '';
      }

      $items[] = [
        'plugin_id' => $plugin_id,
        'configuration' => $configuration,
      ];
    }

    return $items;
  }

}
