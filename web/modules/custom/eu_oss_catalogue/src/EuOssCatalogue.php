<?php

declare(strict_types = 1);

namespace Drupal\eu_oss_catalogue;

use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\eu_oss_catalogue\Entity\OssSolutionInterface;
use Drupal\eu_oss_catalogue\Event\EuOssPostFetchEvent;
use Drupal\eu_oss_catalogue\Event\EuOssSolutionPreSaveEvent;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Default implementation for `eu_oss_catalogue` service.
 */
class EuOssCatalogue implements EuOssCatalogueInterface {

  /**
   * Constructs a new service instance.
   *
   * @param \Drupal\Component\Plugin\PluginManagerInterface $providerManager
   *   The EC OSS Catalogue provider plugin manager.
   * @param \Drupal\Component\Plugin\PluginManagerInterface $parserManager
   *   The EC OSS Catalogue parser plugin manager.
   * @param \Drupal\Core\Queue\QueueFactory $queueFactory
   *   The queue factory service.
   * @param \Symfony\Contracts\EventDispatcher\EventDispatcherInterface $eventDispatcher
   *   The event dispatcher service.
   */
  public function __construct(
    protected PluginManagerInterface $providerManager,
    protected PluginManagerInterface $parserManager,
    protected QueueFactory $queueFactory,
    protected EventDispatcherInterface $eventDispatcher,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function fetch(string $provider): int {
    $this->checkProvider($provider);

    /** @var \Drupal\eu_oss_catalogue\EuOssCatalogueProviderInterface $plugin */
    $plugin = $this->providerManager->createInstance($provider);

    $queue = $this->queueFactory->get('eu_oss_catalogue');
    $queue->createQueue();

    $fetched = 0;
    foreach ($plugin->fetch() as $fetchedSolution) {
      if (!$fetchedSolution instanceof EuOssCatalogueFetchedSolution) {
        throw new \Exception("Invalid data received from '$provider' plugin");
      }

      // Allow 3rd-party to modify the fetched solution.
      $this->eventDispatcher->dispatch(new EuOssPostFetchEvent($fetchedSolution));

      // @todo Move the process of queueing to a separate method for the sake of
      //   making the whole API more clear?
      $queue->createItem([
        'operation' => 'solution:create',
        'provider' => $provider,
        'solution' => $fetchedSolution,
      ]);
      $fetched++;
    }

    return $fetched;
  }

  /**
   * {@inheritdoc}
   */
  public function createOrUpdateSolution(string $providerPluginId, EuOssCatalogueFetchedSolution $fetchedSolution): OssSolutionInterface {
    $this->checkProvider($providerPluginId);
    /** @var \Drupal\eu_oss_catalogue\EuOssCatalogueProviderInterface $provider */
    $provider = $this->providerManager->createInstance($providerPluginId);
    /** @var \Drupal\eu_oss_catalogue\EuOssCatalogueParserInterface $parser */
    $parser = $this->parserManager->createInstance($provider->getPluginDefinition()['parser']);

    // The parser is responsible to create the solution node entity.
    $solution = $parser->parse($fetchedSolution, $provider);

    // Let the provider make their own adjustments, if case.
    $provider->alterSolution($solution, $fetchedSolution);

    // Finally, allow 3rd-party to add their alters.
    $this->eventDispatcher->dispatch(new EuOssSolutionPreSaveEvent($solution, $fetchedSolution));

    $solution->save();

    return $solution;
  }

  /**
   * Checks whether a provider plugin ID is valid.
   *
   * @param string $provider
   *   The provider plugin ID.
   *
   * @throws \InvalidArgumentException
   *   When the provider is not a valid plugin ID.
   */
  protected function checkProvider(string $provider): void {
    if (!$this->providerManager->hasDefinition($provider)) {
      throw new \InvalidArgumentException("The '$provider' provider plugin doesn't exist");
    }
  }

}
