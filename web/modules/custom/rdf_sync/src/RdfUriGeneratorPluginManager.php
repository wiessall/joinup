<?php

declare(strict_types = 1);

namespace Drupal\rdf_sync;

use Drupal\Component\Plugin\FallbackPluginManagerInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\rdf_sync\Annotation\RdfUriGenerator;

/**
 * Plugin manager for RDF URI generator plugins.
 */
class RdfUriGeneratorPluginManager extends DefaultPluginManager implements FallbackPluginManagerInterface {

  /**
   * Builds a new plugin manager instance.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct('Plugin/rdf_sync/RdfUriGenerator', $namespaces, $module_handler, RdfUriGeneratorPluginInterface::class, RdfUriGenerator::class);
    $this->alterInfo('rdf_uri_generator_info');
    $this->setCacheBackend($cache_backend, 'rdf_uri_generator_plugins');
  }

  /**
   * {@inheritdoc}
   */
  public function getFallbackPluginId($plugin_id, array $configuration = []): string {
    return 'default';
  }

}
