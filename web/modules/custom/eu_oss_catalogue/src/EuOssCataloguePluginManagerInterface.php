<?php

declare(strict_types = 1);

namespace Drupal\eu_oss_catalogue;

use Drupal\Component\Plugin\PluginManagerInterface;

/**
 * Interface for module's plugin managers.
 */
interface EuOssCataloguePluginManagerInterface extends PluginManagerInterface {

  /**
   * Returns the list of definition as options.
   *
   * @return array
   *   An option list having the plugin ID as key and the label as value.
   */
  public function getDefinitionsAsOptions(): array;

}
