<?php

declare(strict_types=1);

namespace Drupal\eu_oss_catalogue\Plugin\QueueWorker;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\eu_oss_catalogue\EuOssCatalogueInterface;
use Drupal\eu_oss_catalogue\EuOssCataloguePluginManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Consumes the 'eu_oss_catalogue' queue.
 *
 * @QueueWorker(
 *   id = "eu_oss_catalogue_fetcher",
 *   title = @Translation("EU OSS Catalogue fetcher"),
 *   cron = {"time" = 10},
 * )
 */
class EuOssCatalogueFetcher extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    $pluginId,
    $pluginDefinition,
    protected EuOssCataloguePluginManagerInterface $providerPluginManager,
  ) {
    parent::__construct($configuration, $pluginId, $pluginDefinition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('plugin.manager.eu_oss_catalogue.provider'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data): void {}

}
