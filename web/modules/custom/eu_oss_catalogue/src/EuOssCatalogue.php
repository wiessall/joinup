<?php

declare(strict_types=1);

namespace Drupal\eu_oss_catalogue;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Utility\Error;
use Drupal\eu_oss_catalogue\Entity\OssSolutionInterface;
use Drupal\eu_oss_catalogue\Event\EuOssPostFetchEvent;
use Drupal\eu_oss_catalogue\Event\EuOssSolutionPreSaveEvent;
use Drupal\eu_oss_catalogue\Exception\HostingPlatformFetchException;
use Drupal\eu_oss_catalogue\Model\Project;
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

    $solutionUrlsToProcess = [];
    $solutionUrlsDiscovered = [];
    try {
      while (($projects = $provider->fetch())->valid()) {
        foreach ($projects as $project) {
          \assert($project instanceof Project);

          // Give parser the chance to do the validation.
          if ($errors = $provider->getParser()->isValid($project)) {
            $errorMarkup = '<ul><li>' . implode('</li><li>', $errors) . '</li></ul>';
            $message = <<<MESSAGE
            <p>Provider: @provider (<code>@provider_id</code>)</p>
            <p>Provider's reference: <code>@reference</code></p>
            <p>Schema errors:</p>
            $errorMarkup
            MESSAGE;
            $arguments = [
              '@provider' => $provider->getPluginDefinition()['label'],
              '@provider_id' => $providerId,
              '@reference' => $project->platformReference ?? '[not provided]',
            ];
            $this->logger->error($message, $arguments);
            continue;
          }

          // Cancellation should also cause removing this provider from the
          // preexisting OSS Solution entity.
          $solutionUrlsDiscovered[] = $project->url;
          if (!$this->fetchedSolutionNeedsUpdate($project, $provider)) {
            continue;
          }

          // Allow 3rd-party to modify or cancel the fetched solution.
          $event = new EuOssPostFetchEvent($project);
          $this->eventDispatcher->dispatch($event);
          if ($event->isCancelled()) {
            // @todo Needs tests.
            $this->logger->warning("Solution '@solution' (@url) import cancelled by a subscriber. Reason: @reason", [
              '@solution' => $project->name,
              '@url' => $project->url,
              '@reason' => $event->getCancellationReason(),
            ]);
            continue;
          }

          // Providers might provide the same solution (with the same URL) more
          // than once; e.g. Developers Italia has a package three times with
          // slightly different data. So if we already queued on item, we won't
          // queue its second occurrence one more time, but instead, at the end
          // of this method, we log a message about the duplicated URLs.
          if (in_array($project->url, $solutionUrlsToProcess, TRUE)) {
            $this->logger->warning(
              "Solution '@solution' (@url) import skipped: URL already seen in the same provider.",
              [
                '@solution' => $project->name,
                '@url' => $project->url,
              ]
            );
            continue;
          }

          // @todo Move the process of queueing to a separate method for the sake
          //   of making the whole API more clear?
          $queue->createItem([
            'operation' => 'solution:create',
            'provider' => $providerId,
            'solution' => $project,
          ]);
          // Keep track of solutions been queued already.
          $solutionUrlsToProcess[] = $project->url;
        }
      }
    }
    catch (HostingPlatformFetchException $exception) {
    }

    // Check duplicate URLs and log if there are any.
    $urlCounts = array_count_values($solutionUrlsDiscovered);
    $nonUniqueUrls = array_filter(
      $urlCounts,
      fn (int $count) => $count > 1
    );
    if ($nonUniqueUrls) {
      $this->logger->warning(
        "Non-unique solution URLs from provider '@provider': @non-unique-urls.", [
          '@provider' => $provider->getPluginId(),
          '@non-unique-urls' => implode(
            ', ',
            array_map(
              fn(string $url, int $count) => "$url ($count)",
              array_keys($nonUniqueUrls),
              $nonUniqueUrls
            )
          ),
        ],
      );
    }

    // @todo Maybe this should be a flag that enables cleanup deletion?
    if (!isset($exception)) {
      $queue->createItem([
        'operation' => 'solution:create:cleanup',
        'provider' => $providerId,
        'solution_urls' => array_keys($urlCounts),
      ]);
    }
    else {
      // We have unstable API behind the current @EuOssCatalogueProvider
      // plugin instance.
      $this->logger->error(
        "Unstable API behind '@provider' provider plugin, skipping cleanup. Exceptions:\n@exception-messages",
        [
          '@provider' => $provider->getPluginId(),
          '@exception-messages' => $exception->getMessage(),
        ],
      );
    }

    return count($urlCounts);
  }

  /**
   * {@inheritdoc}
   */
  public function createOrUpdateSolution(string $providerPluginId, Project $project): ?OssSolutionInterface {
    /** @var \Drupal\eu_oss_catalogue\EuOssCatalogueProviderInterface $provider */
    $provider = $this->providerManager->createInstance($providerPluginId);
    /** @var \Drupal\eu_oss_catalogue\EuOssCatalogueParserInterface $parser */
    $parser = $this->parserManager->createInstance($provider->getPluginDefinition()['parser']);

    // The parser is responsible to create the solution node entity.
    if (!$solution = $parser->parse($project, $provider)) {
      return NULL;
    }

    // Let the provider make their own adjustments, if case.
    $provider->alterSolution($solution, $project);

    // Finally, allow 3rd-party to add their alters.
    $this->eventDispatcher->dispatch(new EuOssSolutionPreSaveEvent($solution, $project));

    // Set source hash.
    $solution->set('oss_source_hash', $project->getHash());

    // Make sure it is not archived.
    $solution->set('oss_archived', 0);

    try {
      $operationResult = $solution->save();
    }
    catch (\Exception $exception) {
      // Solution could not be saved.
      Error::logException($this->logger, $exception);
      return NULL;
    }

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
    $query = $nodeStorage
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('oss_source', $providerPluginId)
      ->condition('type', 'oss_solution');
    if ($solutionUrls) {
      $query->condition('oss_git_url', $solutionUrls, 'NOT IN');
    }
    $removedSolutionIds = $query->execute();

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
        $solution->set('oss_archived', 1);
        $solution->save();
        $this->logger->info("Solution '@solution' (@url) was archived due to having no active providers", [
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

  /**
   * Determines whether solution node needs to be created/updated.
   *
   * @param \Drupal\eu_oss_catalogue\Model\Project $project
   *   The project value object.
   * @param \Drupal\eu_oss_catalogue\EuOssCatalogueProviderInterface $provider
   *   The provider plugin instance which fetched the solution.
   *
   * @return bool
   *   Whether solution node needs to be created/updated.
   */
  protected function fetchedSolutionNeedsUpdate(Project $project, EuOssCatalogueProviderInterface $provider): bool {
    $hash = $project->getHash();
    \assert(strlen($hash) === 43);

    // Try to get solution node by the hash.
    $solutionNodes = $this->entityTypeManager->getStorage('node')
      ->loadByProperties([
        'type' => 'oss_solution',
        'oss_source_hash' => $hash,
        // Also have to take the provider reference into consideration.
        'oss_source' => $provider->getPluginId(),
        // Limit only to not archived solutions.
        'oss_archived' => 0,
      ]);

    return empty($solutionNodes);
  }

}
