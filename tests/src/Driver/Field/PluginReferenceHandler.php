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
  protected ?string $mainProperty = 'plugin_id';

  /**
   * {@inheritdoc}
   */
  protected function doExpand(array $records): array {
    $items = [];
    foreach ($records as $record) {
      $items[] = [
        'plugin_id' => (string) ($record['plugin_id'] ?? ''),
        'configuration' => $record['configuration'] ?? '',
      ];
    }
    return $items;
  }

}
