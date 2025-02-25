<?php

declare(strict_types=1);

namespace Drupal\joinup_oss_catalogue\ParamConverter;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\ParamConverter\ParamConverterInterface;
use Drupal\eu_oss_catalogue\EuOssCataloguePluginManagerInterface;
use Drupal\eu_oss_catalogue\EuOssCatalogueProviderInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Routing\Route;

/**
 * Converts route params of type 'hosting_platform'.
 */
readonly class CatalogueSourceConverter implements ParamConverterInterface {

  public function __construct(
    #[Autowire(service: 'plugin.manager.eu_oss_catalogue.provider')]
    protected EuOssCataloguePluginManagerInterface $providerPluginManager,
  ) {}

  /**
   * Converts the plugin ID to a plugin instance.
   */
  public function convert($value, $definition, $name, array $defaults): ?EuOssCatalogueProviderInterface {
    try {
      if (!$this->providerPluginManager->hasDefinition($value)) {
        return NULL;
      }

      return $this->providerPluginManager->createInstance($value);
    }
    catch (PluginException) {
      return NULL;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function applies($definition, $name, Route $route): bool {
    return isset($definition['type']) && $definition['type'] === 'hosting_platform';
  }

}
