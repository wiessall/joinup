<?php

declare(strict_types = 1);

namespace Drupal\eu_oss_catalogue;

use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
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
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   */
  public function __construct(
    protected PluginManagerInterface $providerManager,
    protected PluginManagerInterface $parserManager,
    protected QueueFactory $queueFactory,
    protected EventDispatcherInterface $eventDispatcher,
    protected EntityTypeManagerInterface $entityTypeManager,
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
    $solutionUrls = [];
    while (($fetchedSolutions = $plugin->fetch())->valid()) {
      foreach ($fetchedSolutions as $fetchedSolution) {
        if (!$fetchedSolution instanceof EuOssCatalogueFetchedSolution) {
          throw new \Exception("Invalid data received from '$provider' plugin");
        }
        $solutionUrls[] = $fetchedSolution->getUrl();

        // Do not import solutions without a valid schema. However, we still add
        // them to the list of solutions above, before the check, so that we can
        // prevent deleting an entity that was updated upstream and is no longer
        // valid to us.
        if (!$fetchedSolution->hasValidSchema()) {
          continue;
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
    }

    // @todo Maybe this should be a flag that enables cleanup deletion?
    $queue->createItem([
      'operation' => 'solution:create:cleanup',
      'provider' => $provider,
      'solution_urls' => $solutionUrls,
    ]);

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
   * {@inheritdoc}
   */
  public function cleanupSetup(string $providerPluginId, array $solutionUrls): void {
    $this->checkProvider($providerPluginId);

    $nodeStorage = $this->entityTypeManager->getStorage('node');

    // The parser is responsible to create the solution node entity.
    $removedSolutionIds = $nodeStorage
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('oss_git_url', $solutionUrls, 'NOT IN')
      ->condition('oss_source', $providerPluginId)
      ->condition('type', 'oss_solution')
      ->execute();

    if (empty($removedSolutionIds)) {
      return;
    }

    $queue = $this->queueFactory->get('eu_oss_catalogue');
    $queue->createQueue();

    // Use a queue as - though improbable - the list might be too long.
    while ($chunk = array_splice($removedSolutionIds, 0, 10)) {
      $queue->createItem([
        'operation' => 'solution:provider_remove',
        'provider' => $providerPluginId,
        'solution_ids' => $chunk,
      ]);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function cleanupOrUpdate(string $providerPluginId, string $solutionIds): void {
    $this->checkProvider($providerPluginId);

    /** @var \Drupal\eu_oss_catalogue\EuOssCatalogueProviderInterface $provider */
    $provider = $this->providerManager->createInstance($providerPluginId);

    /** @var \Drupal\eu_oss_catalogue\Entity\OssSolutionInterface $solution */
    foreach ($this->entityTypeManager->getStorage('node')->loadMultiple($solutionIds) as $solution) {
      if ($solution->removeProvider($provider) === 0) {
        $solution->delete();
      }
      else {
        $solution->save();
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function loadBySolutionUrl(string $solutionUrl): ?OssSolutionInterface {
    $entities = $this->entityTypeManager->getStorage('node')->loadByProperties(['oss_git_url' => $solutionUrl]);
    return reset($entities) ?: NULL;
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
