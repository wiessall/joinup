<?php

declare(strict_types = 1);

namespace Drupal\eu_oss_catalogue;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;

/**
 * Provides the plugin manager for EU OSS Catalogue plugins types.
 */
class EuOssCataloguePluginManager extends DefaultPluginManager implements EuOssCataloguePluginManagerInterface {

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
   * @param string $type
   *   The type of plugins: provider, parser, resolver.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler, string $type) {
    \assert(in_array($type, ['provider', 'resolver', 'parser'], TRUE));
    $typeCapitalized = ucfirst($type);
    $interfaceClass = 'Drupal\eu_oss_catalogue\EuOssCatalogue' . $typeCapitalized . 'Interface';
    $annotationClass = 'Drupal\eu_oss_catalogue\Annotation\EuOssCatalogue' . $typeCapitalized;

    parent::__construct('Plugin/eu_oss_catalogue/' . $typeCapitalized, $namespaces, $module_handler, $interfaceClass, $annotationClass);

    $this->alterInfo("eu_oss_catalogue_{$type}_info");
    $this->setCacheBackend($cache_backend, "eu_oss_catalogue_{$type}_plugins");
  }

}
