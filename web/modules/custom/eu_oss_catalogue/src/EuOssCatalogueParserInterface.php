<?php

declare(strict_types = 1);

namespace Drupal\eu_oss_catalogue;

use Drupal\Component\Plugin\PluginInspectionInterface;

/**
 * Provides an interface for parser plugins.
 */
interface EuOssCatalogueParserInterface extends PluginInspectionInterface {

  /**
   * Checks whether the schema is valid.
   *
   * @param array $data
   *   Data to be checked.
   * @param \Drupal\eu_oss_catalogue\EuOssCatalogueProviderInterface $provider
   *   The provider plugin instance.
   * @param string|null $providerAssignedId
   *   Some providers are assigning a proprietary ID to the solution. This is
   *   particularly useful because schema errors can be reported back to the
   *   provider.
   *
   * @return bool
   *   TRUE if the schema is valid.
   */
  public function checkSchema(array $data, EuOssCatalogueProviderInterface $provider, ?string $providerAssignedId = NULL): bool;

}
