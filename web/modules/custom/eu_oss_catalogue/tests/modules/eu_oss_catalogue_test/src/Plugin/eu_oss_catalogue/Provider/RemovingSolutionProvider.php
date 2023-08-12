<?php

declare(strict_types = 1);

namespace Drupal\eu_oss_catalogue_test\Plugin\eu_oss_catalogue\Provider;

use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Serialization\Yaml;
use Drupal\eu_oss_catalogue\EuOssCatalogueFetchedSolution;
use Drupal\eu_oss_catalogue\Plugin\eu_oss_catalogue\Provider\DevelopersItalia;
use GuzzleHttp\Client;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provider to be used in testing.
 *
 * Same as developers_italia but we need a different name.
 *
 * @EuOssCatalogueProvider(
 *   id = "removing_solution_provider",
 *   label = "Removing solution provider",
 *   parser = "publiccode_yml",
 * )
 */
class RemovingSolutionProvider extends DevelopersItalia {

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
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(
    array $configuration,
    $pluginId,
    $pluginDefinition,
    protected Client $httpClient,
    protected PluginManagerInterface $parserManager,
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct($configuration, $pluginId, $pluginDefinition, $httpClient, $parserManager);
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
      $container->get('entity_type.manager')
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
        $hasValidSchema = $this->checkSchema($decoded, $newItem['id']);

        // A solution source URL is necessary to fetch the data.
        if (!isset($decoded['url'])) {
          continue;
        }

        // If the title is "Solution 2" and the solution exists, skip it so that
        // we simulate a solution that has been removed.
        if ($decoded['name'] === 'Solution 2') {
          $ids = $this->entityTypeManager->getStorage('node')->getQuery()
            ->accessCheck(FALSE)
            ->condition('title', 'Solution 2')
            ->execute();

          if (!empty($ids)) {
            continue;
          }
        }

        yield new EuOssCatalogueFetchedSolution(
          $decoded['url'],
          $decoded['name'],
          $decoded,
          $hasValidSchema,
          $newItem['id'],
        );
      }
    }

    $this->next = NULL;
  }

}
