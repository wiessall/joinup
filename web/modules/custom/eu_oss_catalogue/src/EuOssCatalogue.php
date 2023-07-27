<?php

declare(strict_types = 1);

namespace Drupal\eu_oss_catalogue;

use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\Queue\QueueFactory;

/**
 * Default implementation for `eu_oss_catalogue` service.
 */
class EuOssCatalogue {

  /**
   * Constructs a new service instance.
   *
   * @param \Drupal\Component\Plugin\PluginManagerInterface $providerManager
   *   The EC OSS Catalogue provider plugin manager.
   * @param \Drupal\Core\Queue\QueueFactory $queueFactory
   *   The queue factory service.
   */
  public function __construct(
    protected PluginManagerInterface $providerManager,
    protected QueueFactory $queueFactory,
  ) {}

  /**
   * Fetches and queues a list of business solutions from a provider.
   *
   * @param string $provider
   *   The provider plugin ID.
   *
   * @throws \InvalidArgumentException
   *   If the passed plugin ID is invalid.
   */
  public function fetch(string $provider): void {
    if (!$this->providerManager->hasDefinition($provider)) {
      throw new \InvalidArgumentException("The '$provider' provider plugin doesn't exist");
    }
    /** @var \Drupal\eu_oss_catalogue\EuOssCatalogueProviderInterface $plugin */
    $plugin = $this->providerManager->createInstance($provider);

    $queue = $this->queueFactory->get('eu_oss_catalogue');
    $queue->createQueue();

    foreach ($plugin->fetch() as $fetchedItem) {
      if (!$fetchedItem instanceof EuOssCatalogueFetchedSolution) {
        throw new \Exception("Invalid data received from '$provider' plugin");
      }
      $queue->createItem(['provider' => $provider, 'fetched' => $fetchedItem]);
    }
  }

}
