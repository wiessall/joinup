<?php

declare(strict_types=1);

namespace Drupal\eu_oss_catalogue\Plugin\QueueWorker;

use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\eu_oss_catalogue\EuOssCatalogueInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Consumes the 'eu_oss_catalogue_fetcher' queue.
 *
 * @QueueWorker(
 *   id = "eu_oss_catalogue_fetcher",
 *   title = @Translation("EU OSS Catalogue fetcher"),
 *   cron = {"time" = 60}
 * )
 */
class EuOssCatalogueFetcher extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    $pluginId,
    $pluginDefinition,
    protected EuOssCatalogueInterface $euOssCatalogue,
    protected LoggerChannelInterface $logger,
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
      $container->get('eu_oss_catalogue'),
      $container->get('logger.channel.eu_oss_catalogue')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data): void {
    try {
      $provider = $data['provider'];

      $this->euOssCatalogue->fetch($provider);
      $this->logger->info('OSS fetch data ' . $provider . ' completed successfully.');
    }
    catch (\Exception $e) {
      $this->logger->error('An error occurred while processing the data retrieval of ' . $provider . ': @error', ['@error' => $e->getMessage()]);
    }
  }

}
