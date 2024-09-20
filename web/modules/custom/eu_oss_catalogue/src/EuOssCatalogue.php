<?php

declare(strict_types=1);

namespace Drupal\eu_oss_catalogue;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Utility\Error;
use Drupal\eu_oss_catalogue\Entity\OssSolutionInterface;
use Drupal\eu_oss_catalogue\Event\EuOssPostFetchEvent;
use Drupal\eu_oss_catalogue\Event\EuOssSolutionPostSaveEvent;
use Drupal\eu_oss_catalogue\Event\EuOssSolutionPreSaveEvent;
use Drupal\eu_oss_catalogue\Exception\HostingPlatformFetchException;
use Drupal\eu_oss_catalogue\Model\Project;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Default implementation for `eu_oss_catalogue` service.
 */
class EuOssCatalogue implements EuOssCatalogueInterface {

  use StringTranslationTrait;

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
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   */
  public function __construct(
    protected EuOssCataloguePluginManagerInterface $providerManager,
    protected EuOssCataloguePluginManagerInterface $parserManager,
    protected QueueFactory $queueFactory,
    protected EventDispatcherInterface $eventDispatcher,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected LoggerChannelInterface $logger,
    protected TimeInterface $time,
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
            $this->logger->error(
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

    $urlCounts = array_count_values($solutionUrlsDiscovered);
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
  public function createOrUpdateSolution(string $providerPluginId, Project $project): void {
    /** @var \Drupal\eu_oss_catalogue\EuOssCatalogueProviderInterface $provider */
    $provider = $this->providerManager->createInstance($providerPluginId);
    /** @var \Drupal\eu_oss_catalogue\EuOssCatalogueParserInterface $parser */
    $parser = $this->parserManager->createInstance($provider->getPluginDefinition()['parser']);

    try {
      // The parser is responsible to create the solution node entity.
      $solution = $parser->parse($project, $provider);

      // Let the provider make their own adjustments, if case.
      $provider->alterSolution($solution, $project);

      // Finally, allow 3rd-party to add their alters.
      $this->eventDispatcher->dispatch(new EuOssSolutionPreSaveEvent($solution, $project));

      // Set source hash.
      $solution->set('oss_source_hash', $project->getHash());

      $silentErrors = implode(", ", $project->getSilentErrors());
      if (!$solution->isNew()) {
        $solution->setNewRevision();
        $solution->setRevisionCreationTime($this->time->getRequestTime());

        $message = match ($solution->get('oss_archived')->getString()) {
          '1' => $this->t('Unarchive, because re-added (present again) in "@provider" source/catalogue. @errors', [
            '@provider' => $provider->getPluginDefinition()['label'],
            '@errors' => $silentErrors,
          ]),
          default => $this->t('Update, because data has been changed in "@provider" source/catalogue. @errors', [
            '@provider' => $provider->getPluginDefinition()['label'],
            '@errors' => $silentErrors,
          ]),
        };
      }
      else {
        $message = $this->t('Import from "@provider" source/catalogue. @errors', [
          '@provider' => $provider->getPluginDefinition()['label'],
          '@errors' => $silentErrors,
        ]);
      }
      $solution->setRevisionLogMessage((string) $message);

      // Make sure it is not archived.
      $solution->set('oss_archived', 0);

      $operationResult = $solution->save();

      // Allow 3rd-party to act after save.
      $this->eventDispatcher->dispatch(new EuOssSolutionPostSaveEvent($solution, $project));
    }
    catch (\Exception $exception) {
      // The solution could not be saved.
      Error::logException($this->logger, $exception);
      return;
    }

    if ($operationResult === SAVED_NEW) {
      $loggerArguments = [
        '@solution' => $solution->label(),
        'oss_landing_url' => $solution->get('oss_landing_url')->getString(),
        'oss_solution' => $solution->id(),
        'oss_source' => $provider->getPluginId(),
      ];
      $this->logger->info('Solution @solution was created.', $loggerArguments);
    }
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
      $solution->setNewRevision();
      $solution->setRevisionCreationTime($this->time->getRequestTime());

      if ($solution->get('oss_source')->isEmpty()) {
        $message = $this->t('Archive, because removed (not present anymore) from "@provider" source/catalogue', [
          '@provider' => $provider->getPluginDefinition()['label'],
        ]);
        $solution->setRevisionLogMessage((string) $message);
        $solution->set('oss_archived', 1);
      }
      else {
        $message = $this->t('Update, because of the only removal "@provider" source/catalogue', [
          '@provider' => $provider->getPluginDefinition()['label'],
        ]);
        $solution->setRevisionLogMessage((string) $message);
      }
      $solution->save();
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
