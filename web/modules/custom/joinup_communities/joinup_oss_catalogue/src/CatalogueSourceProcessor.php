<?php

declare(strict_types=1);

namespace Drupal\joinup_oss_catalogue;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Locale\CountryManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\eu_oss_catalogue\EuOssCatalogueProviderInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Catalogue Source processor.
 */
class CatalogueSourceProcessor implements ContainerInjectionInterface {

  use StringTranslationTrait;

  public function __construct(
    readonly protected CountryManagerInterface $countryManager
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get('country_manager'),
    );
  }

  /**
   * Processes provided.
   *
   * @param \Drupal\eu_oss_catalogue\EuOssCatalogueProviderInterface $provider
   *  The provider.
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
        '#type' => 'processed_text',
        '#text' => (string) $definition['description'],
        '#format' => 'plain_text',
      ],
      'weblinks' => array_map(fn($weblink) => [
        '#type' => 'link',
        '#title' => $weblink['title'] ?? $weblink['url'],
        '#url' => Url::fromUri($weblink['url']),
        '#attributes' => [
          'target' => '_blank',
          'rel' => 'noopener noreferrer',
        ],
      ], $definition['weblinks'] ?? []),
    ];
  }

}
