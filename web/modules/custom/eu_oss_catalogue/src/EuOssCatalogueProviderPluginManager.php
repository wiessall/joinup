<?php

declare(strict_types = 1);

namespace Drupal\eu_oss_catalogue;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\eu_oss_catalogue\Annotation\EuOssCatalogueProvider;

/**
 * Provides the plugin manager for EU OSS Catalogue provider plugins.
 */
class EuOssCatalogueProviderPluginManager extends DefaultPluginManager {

  /**
   * Constructs a new plugin manager instance.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to invoke the alter hook with.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct('Plugin/eu_oss_catalogue/Provider', $namespaces, $module_handler, EuOssCatalogueProviderInterface::class, EuOssCatalogueProvider::class);
    $this->alterInfo('eu_oss_catalogue_provider_info');
    $this->setCacheBackend($cache_backend, 'eu_oss_catalogue_provider_plugins');
  }

}
