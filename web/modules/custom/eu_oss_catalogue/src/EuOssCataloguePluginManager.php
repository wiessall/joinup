<?php

declare(strict_types=1);

namespace Drupal\eu_oss_catalogue;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Component\Utility\SortArray;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Symfony\Component\DependencyInjection\Container;

/**
 * Provides the plugin manager for EU OSS Catalogue plugins types.
 */
class EuOssCataloguePluginManager extends DefaultPluginManager implements EuOssCataloguePluginManagerInterface {

  /**
   * Plugin type IDs.
   *
   * @var string[]
   */
  private const PLUGIN_TYPES = [
    'provider',
    'resolver',
    'parser',
    'hosting_platform',
  ];

  /**
   * Constructs a new plugin manager instance.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cacheBackend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $moduleHandler
   *   The module handler to invoke the alter hook with.
   * @param string $type
   *   The type of plugins: provider, parser, resolver.
   */
  public function __construct(
    \Traversable $namespaces,
    CacheBackendInterface $cacheBackend,
    ModuleHandlerInterface $moduleHandler,
    protected string $type,
  ) {
    \assert(in_array($type, static::PLUGIN_TYPES, TRUE));

    $typeCamelized = Container::camelize($type);
    $interfaceClass = 'Drupal\eu_oss_catalogue\EuOssCatalogue' . $typeCamelized . 'Interface';
    $annotationClass = 'Drupal\eu_oss_catalogue\Annotation\EuOssCatalogue' . $typeCamelized;

    parent::__construct('Plugin/eu_oss_catalogue/' . $typeCamelized, $namespaces, $moduleHandler, $interfaceClass, $annotationClass);

    $this->alterInfo("eu_oss_catalogue_{$type}_info");
    $tags = $type === 'provider' ? ['config:hosting_platform_list'] : [];
    $this->setCacheBackend($cacheBackend, "eu_oss_catalogue_{$type}_plugins", $tags);
  }

  /**
   * {@inheritdoc}
   */
  public function getDefinitionsAsOptions(string $type): array {
    return array_map(
      fn (array $definition): MarkupInterface|string => $definition[$type],
      $this->getDefinitions(),
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function findDefinitions(): array {
    $definitions = parent::findDefinitions();
    if ($this->type !== 'hosting_platform') {
      return $definitions;
    }

    // Order definitions by `apiDetectionWeight` definition entry. Note that we
    // use this method because this happens just before caching the definitions.
    uasort($definitions, [SortArray::class, 'sortByWeightElement']);

    return $definitions;
  }

}
