<?php

declare(strict_types=1);

namespace Drupal\sparql_endpoint;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\sparql_endpoint\Plugin\Attribute\SparqlFormat;
use Drupal\sparql_endpoint\Plugin\SparqlFormat\SparqlFormatInterface;

/**
 * Provides the SPARQL format plugin manager.
 */
class SparqlFormatManager extends DefaultPluginManager {

  /**
   * Constructs a new SparqlFormatManager object.
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
    parent::__construct('Plugin/SparqlFormat', $namespaces, $module_handler, SparqlFormatInterface::class, SparqlFormat::class);

    $this->alterInfo('sparql_endpoint_sparql_format_info');
    $this->setCacheBackend($cache_backend, 'sparql_endpoint_sparql_format_plugins');
  }

  /**
   * Get a SPARQL format plugin by its ID.
   *
   * @param string $pluginId
   *   The ID of the plugin.
   *
   * @return \Drupal\sparql_endpoint\Plugin\SparqlFormat\SparqlFormatInterface|null
   *   The plugin object, or NULL if the plugin was not found.
   */
  public function getSparqlFormatPluginById(string $pluginId): ?SparqlFormatInterface {
    // Check if the plugin exists.
    if ($this->hasDefinition($pluginId)) {
      return $this->createInstance($pluginId);
    }

    // If the plugin was not found, return NULL.
    return NULL;
  }

}
