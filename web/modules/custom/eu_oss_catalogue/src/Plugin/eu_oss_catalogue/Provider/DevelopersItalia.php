<?php

declare(strict_types = 1);

namespace Drupal\eu_oss_catalogue\Plugin\eu_oss_catalogue\Provider;

use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Config\Schema\SchemaCheckTrait;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Serialization\Yaml;
use Drupal\eu_oss_catalogue\EuOssCatalogueFetchedSolution;
use Drupal\eu_oss_catalogue\EuOssCatalogueProviderPluginBase;
use GuzzleHttp\Client;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Developers Italia provider.
 *
 * @EuOssCatalogueProvider(
 *   id = "developers_italia",
 *   label = @Translation("Developers Italia"),
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
   * Constructs a new plugin instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $pluginId
   *   The plugin_id for the plugin instance.
   * @param mixed $pluginDefinition
   *   The plugin implementation definition.
   * @param \GuzzleHttp\Client $httpClient
   *   The HTTP client.
   * @param \Drupal\Component\Plugin\PluginManagerInterface $parserManager
   *   The EU OSS parser plugin manager service.
   */
  public function __construct(
    array $configuration,
    $pluginId,
    $pluginDefinition,
    protected Client $httpClient,
    protected PluginManagerInterface $parserManager,
  ) {
    parent::__construct($configuration, $pluginId, $pluginDefinition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $pluginId, $pluginDefinition): self {
    return new static(
      $configuration,
      $pluginId,
      $pluginDefinition,
      $container->get('http_client'),
      $container->get('plugin.manager.eu_oss_catalogue.parser'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function fetch(): array {
    $data = [];
    $next = '';

    do {
      $url = self::ENDPOINT . $next . ($next ? '&' : '?') . 'page[size]=500';
      $response = $this->httpClient->get($url);
      $read = Json::decode($response->getBody()->getContents());

      $next = $read['links']['next'];

      foreach ($read['data'] as $newItem) {
        $active = $newItem['active'] ?? FALSE;
        if ($active) {
          $decoded = Yaml::decode($newItem['publiccodeYml']);
          // Only fetch data that adhere to their schema.
          if ($this->checkSchema($decoded, $newItem['id'])) {
            $data[] = new EuOssCatalogueFetchedSolution(
              $decoded['url'],
              $decoded['name'],
              $decoded,
              $newItem['id'],
            );
          }
        }
      }
    } while (!is_null($next));

    return $data;
  }

  /**
   * Checks whether the schema is valid.
   *
   * @param array $data
   *   Data to be checked.
   * @param string|null $providerAssignedId
   *   Some providers are assigning a proprietary ID to the solution.
   *
   * @return bool
   *   Whether the schema is valid.
   */
  protected function checkSchema(array $data, ?string $providerAssignedId): bool {
    /** @var \Drupal\eu_oss_catalogue\EuOssCatalogueParserInterface $parser */
    $parser = $this->parserManager->createInstance($this->getPluginDefinition()['parser']);
    return $parser->checkSchema($data, $this, $providerAssignedId);
  }

}
