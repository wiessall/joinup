<?php

declare(strict_types = 1);

namespace Drupal\eu_oss_catalogue;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Serialization\Yaml;
use Drupal\eu_oss_catalogue\Entity\OssSolutionInterface;
use Drupal\eu_oss_catalogue\Event\EuOssPostFetchEvent;
use Drupal\eu_oss_catalogue\Event\EuOssSolutionPreSaveEvent;
use Drupal\eu_oss_catalogue\Model\EuOssCatalogueFetchedSolution;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Default implementation for `eu_oss_catalogue` service.
 */
class EuOssCatalogue implements EuOssCatalogueInterface {

  /**
   * Constructs a new service instance.
   *
   * @param \Drupal\eu_oss_catalogue\EuOssCataloguePluginManagerInterface $providerManager
   *   The EC OSS Catalogue provider plugin manager.
   * @param \Drupal\eu_oss_catalogue\EuOssCataloguePluginManagerInterface $parserManager
   *   The EC OSS Catalogue parser plugin manager.
   * @param \Drupal\Core\Queue\QueueFactory $queueFactory
   *   The queue factory service.
   * @param \Symfony\Contracts\EventDispatcher\EventDispatcherInterface $eventDispatcher
   *   The event dispatcher service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   *   The EU OSS Catalogue channel logger.
   */
  public function __construct(
    protected EuOssCataloguePluginManagerInterface $providerManager,
    protected EuOssCataloguePluginManagerInterface $parserManager,
    protected QueueFactory $queueFactory,
    protected EventDispatcherInterface $eventDispatcher,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected LoggerChannelInterface $logger,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function fetch(string $providerId): int {
    /** @var \Drupal\eu_oss_catalogue\EuOssCatalogueProviderInterface $provider */
    $provider = $this->providerManager->createInstance($providerId);

    $queue = $this->queueFactory->get('eu_oss_catalogue');
    $queue->createQueue();

    $fetched = 0;
    $solutionUrls = [];
    while (($fetchedSolutions = $provider->fetch())->valid()) {
      foreach ($fetchedSolutions as $fetchedSolution) {
        if (!$fetchedSolution instanceof EuOssCatalogueFetchedSolution) {
          throw new \Exception("Invalid data received from '$providerId' plugin");
        }

        // Don't federate solutions with invalid schema but log them.
        if ($errors = $provider->getParser()->checkSchema($fetchedSolution)) {
          $errorMarkup = '<ul><li>' . implode('</li><li>', $errors) . '</li></ul>';
          $message = <<<MESSAGE
            <p>Provider: @provider (<code>@provider_id</code>)</p>
            <p>Provider's reference: <code>@reference</code></p>
            <p>Schema errors:</p>
            $errorMarkup
            <p>Full data:</p>
            <pre>
            @data
            </pre>
            MESSAGE;
          $arguments = [
            '@provider' => $provider->getPluginDefinition()['label'],
            '@provider_id' => $providerId,
            '@reference' => $fetchedSolution->providerReference ?? '[not provided]',
            '@data' => Yaml::encode($fetchedSolution->data),
          ];
          $this->logger->error($message, $arguments);
          continue;
        }

        // Allow 3rd-party to modify or cancel the fetched solution.
        $event = new EuOssPostFetchEvent($fetchedSolution);
        $this->eventDispatcher->dispatch($event);
        if ($event->isCancelled()) {
          // @todo Needs tests.
          $this->logger->warning("Solution '@solution' (@url) import cancelled by a subscriber. Reason: @reason", [
            '@solution' => $fetchedSolution->name,
            '@url' => $fetchedSolution->url,
            '@reason' => $event->getCancellationReason(),
          ]);
          continue;
        }

        // @todo Move the process of queueing to a separate method for the sake
        //   of making the whole API more clear?
        $queue->createItem([
          'operation' => 'solution:create',
          'provider' => $providerId,
          'solution' => $fetchedSolution,
        ]);
        $fetched++;

        $solutionUrls[] = $fetchedSolution->url;
      }
    }

    // @todo Maybe this should be a flag that enables cleanup deletion?
    $queue->createItem([
      'operation' => 'solution:create:cleanup',
      'provider' => $providerId,
      'solution_urls' => $solutionUrls,
    ]);

    return $fetched;
  }

  /**
   * {@inheritdoc}
   */
  public function createOrUpdateSolution(string $providerPluginId, EuOssCatalogueFetchedSolution $fetchedSolution): OssSolutionInterface {
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

    $operationResult = $solution->save();

    $loggerArguments = [
      '@solution' => $solution->label(),
    ];
    switch ($operationResult) {
      case SAVED_NEW:
        $this->logger->info('Solution @solution was created.', $loggerArguments);
        break;
      default:
        $this->logger->info('Solution @solution was updated.', $loggerArguments);
    }

    return $solution;
  }

  /**
   * {@inheritdoc}
   */
  public function prepareCleanup(string $providerPluginId, array $solutionUrls): void {
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
  public function cleanupOrUpdate(string $providerPluginId, array $solutionIds = []): void {
    /** @var \Drupal\eu_oss_catalogue\EuOssCatalogueProviderInterface $provider */
    $provider = $this->providerManager->createInstance($providerPluginId);

    /** @var \Drupal\eu_oss_catalogue\Entity\OssSolutionInterface $solution */
    foreach ($this->entityTypeManager->getStorage('node')->loadMultiple($solutionIds) as $solution) {
      $solution->removeProvider($provider);
      if ($solution->get('oss_source')->isEmpty()) {
        $solution->delete();
        $this->logger->info("Solution '@solution' (@url) was removed due to having no active providers", [
          '@solution' => $solution->label(),
          '@url' => $solution->get('oss_git_url')->value,
        ]);
      }
      else {
        $solution->save();
        $this->logger->info("Solution '@solution' (@url) had the following provider removed from its sources: @provider.", [
          '@solution' => $solution->label(),
          '@url' => $solution->get('oss_git_url')->value,
          '@provider' => $provider->getPluginDefinition()['label'],
        ]);
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

}
