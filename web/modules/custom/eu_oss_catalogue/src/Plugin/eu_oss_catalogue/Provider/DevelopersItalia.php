<?php

declare(strict_types = 1);

namespace Drupal\eu_oss_catalogue\Plugin\eu_oss_catalogue\Provider;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Config\Schema\SchemaCheckTrait;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Serialization\Yaml;
use Drupal\eu_oss_catalogue\EuOssCataloguePluginManagerInterface;
use Drupal\eu_oss_catalogue\EuOssCatalogueProviderPluginBase;
use Drupal\eu_oss_catalogue\Model\EuOssCatalogueFetchedSolution;
use GuzzleHttp\Client;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Developers Italia provider.
 *
 * @EuOssCatalogueProvider(
 *   id = "developers_italia",
 *   label = @Translation("Developers Italia"),
 *   description = @Translation("Italian National OSS Catalogue, by https://developers.italia.it website .”."),
 *   parser = "publiccode_yml",
 * )
 */
class DevelopersItalia extends EuOssCatalogueProviderPluginBase implements ContainerFactoryPluginInterface {

  use SchemaCheckTrait {
    checkValue as traitCheckValue;
  }

  /**
   * Developers Italia API endpoint.
   *
   * @var string
   */
  protected const ENDPOINT = 'https://api.developers.italia.it/v1/software';

  /**
   * The query string to be used by the next call.
   *
   * @var string|null
   */
  protected ?string $next = '';

  /**
   * Constructs a new plugin instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $pluginId
   *   The plugin_id for the plugin instance.
   * @param mixed $pluginDefinition
   *   The plugin implementation definition.
   * @param \Drupal\eu_oss_catalogue\EuOssCataloguePluginManagerInterface $parserManager
   *   The EU OSS Catalogue parser plugin manager service.
   * @param \GuzzleHttp\Client $httpClient
   *   The HTTP client.
   */
  public function __construct(
    array $configuration,
    $pluginId,
    $pluginDefinition,
    protected EuOssCataloguePluginManagerInterface $parserManager,
    protected Client $httpClient,
  ) {
    parent::__construct($configuration, $pluginId, $pluginDefinition, $parserManager);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $pluginId, $pluginDefinition): self {
    return new static(
      $configuration,
      $pluginId,
      $pluginDefinition,
      $container->get('plugin.manager.eu_oss_catalogue.parser'),
      $container->get('http_client'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function fetch(): iterable {
    if ($this->next === NULL) {
      return;
    }

    $url = static::ENDPOINT . $this->next . ($this->next ? '&' : '?') . 'page[size]=100';
    $response = $this->httpClient->get($url);
    $read = Json::decode($response->getBody()->getContents());

    foreach ($read['data'] as $newItem) {
      $active = $newItem['active'] ?? FALSE;
      if ($active) {
        $decoded = Yaml::decode($newItem['publiccodeYml']);
        yield new EuOssCatalogueFetchedSolution(
          $decoded['url'],
          $decoded['name'],
          $decoded,
          $newItem['id'],
        );
      }
    }

    $this->next = $read['links']['next'];
  }

}
