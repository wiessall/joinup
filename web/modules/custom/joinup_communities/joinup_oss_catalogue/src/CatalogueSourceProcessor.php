<?php

declare(strict_types=1);

namespace Drupal\joinup_oss_catalogue;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Link;
use Drupal\Core\Locale\CountryManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\eu_oss_catalogue\EuOssCataloguePluginManagerInterface;
use Drupal\eu_oss_catalogue\EuOssCatalogueProviderInterface;
use Drupal\joinup_oss_catalogue\ParamConverter\CatalogueSourceConverter;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Catalogue Source processor.
 */
class CatalogueSourceProcessor implements ContainerInjectionInterface {

  use StringTranslationTrait;

  public function __construct(
    protected readonly CountryManagerInterface $countryManager,
    #[Autowire(service: 'plugin.manager.eu_oss_catalogue.provider')]
    protected readonly EuOssCataloguePluginManagerInterface $providerPluginManager,
    #[Autowire(service: 'Drupal\joinup_oss_catalogue\ParamConverter\CatalogueSourceConverter')]
    protected readonly CatalogueSourceConverter $converter,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get('country_manager'),
      $container->get('plugin.manager.eu_oss_catalogue.provider'),
      $container->get('Drupal\joinup_oss_catalogue\ParamConverter\CatalogueSourceConverter'),
    );
  }

  /**
   * Processes provided.
   *
   * @param \Drupal\eu_oss_catalogue\EuOssCatalogueProviderInterface $provider
   *   The provider.
   *
   * @return array
   *   Render array for source provider.
   */
  public function processProvider(EuOssCatalogueProviderInterface $provider): array {
    $definition = $provider->getPluginDefinition();
    $list = $this->countryManager->getList();

    return [
      'label' => (string) $definition['label'],
      'type' => $definition['type']?->getLabel(),
      'country' => $list[$definition['country']] ?? NULL,
      'description' => [
        'data' => [
          '#type' => 'processed_text',
          '#text' => (string) $definition['description'],
          '#format' => 'plain_text',
        ],
      ],
      'weblinks' => !empty($definition['weblinks']) ? [
        'data' => array_map(fn($weblink) => Link::fromTextAndUrl(
          $weblink['title'] ?? $weblink['url'],
          Url::fromUri($weblink['url'])
        )->toRenderable(), $definition['weblinks']),
      ] : NULL,
    ];
  }

  /**
   * Returns all EU OSS Catalogue providers.
   *
   * @return array
   *   Returns array of EuOssCatalogueProviderInterface providers.
   */
  public function getAllProviders(): array {
    $rows = [];
    foreach ($this->providerPluginManager->getDefinitions() as $pluginId => $definition) {
      $provider = $this->converter->convert($pluginId, $definition, 'plugin', []);
      $rows[$pluginId] = $this->processProvider($provider);
    }
    uasort($rows, function ($a, $b) {
      return strcmp($a['label'], $b['label']);
    });
    return $rows;
  }

}
