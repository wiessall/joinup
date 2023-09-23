<?php

declare(strict_types = 1);

namespace Drupal\eu_oss_catalogue_test\Plugin\eu_oss_catalogue\Provider;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\eu_oss_catalogue\EuOssCataloguePluginManagerInterface;
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
   * @param \Drupal\eu_oss_catalogue\EuOssCataloguePluginManagerInterface $parserManager
   *   The EU OSS Catalogue parser plugin manager service.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   *   The EU OSS Catalogue logger.
   * @param \GuzzleHttp\Client $httpClient
   *   The HTTP client.
   * @param \Drupal\eu_oss_catalogue\EuOssCataloguePluginManagerInterface $platformManager
   *   The EU OSS Catalogue code hosting platform plugin manager service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(
    array $configuration,
    $pluginId,
    $pluginDefinition,
    EuOssCataloguePluginManagerInterface $parserManager,
    protected LoggerChannelInterface $logger,
    Client $httpClient,
    protected EuOssCataloguePluginManagerInterface $platformManager,
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct($configuration, $pluginId, $pluginDefinition, $parserManager, $logger, $httpClient, $platformManager);
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
      $container->get('logger.channel.eu_oss_catalogue'),
      $container->get('http_client'),
      $container->get('plugin.manager.eu_oss_catalogue.hosting_platform'),
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function fetch(): iterable {
    foreach (parent::fetch() as $fetchedSolution) {
      // If the title is "Solution 2" and the solution exists, skip it so that
      // we simulate a solution that has been removed.
      if ($fetchedSolution->name === 'Solution 2') {
        $ids = $this->entityTypeManager->getStorage('node')->getQuery()
          ->accessCheck(FALSE)
          ->condition('title', 'Solution 2')
          ->execute();

        if (!empty($ids)) {
          continue;
        }
      }

      yield $fetchedSolution;
    }
  }

}
