<?php

declare(strict_types=1);

namespace Drupal\joinup_oss_catalogue\Hook;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\State\StateInterface;
use Drupal\eu_oss_catalogue\Entity\OssSolutionInterface;
use Drupal\joinup_oss_catalogue\JoinupOssCatalogueImagecacheWarmer;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Contains hook implementations of Joinup OSS Catalogue module.
 */
class JoinupOssCatalogueHooks {

  protected const int OSS_REMOTE_IMAGE_CACHE_REFRESH_INTERVAL = 24 * 60 * 60;

  protected const string LAST_REFRESH_TIME_KEY = 'joinup_oss_catalogue.imagecache.last_refresh';

  protected const string LAST_REFRESH_FINISHED_KEY = 'joinup_oss_catalogue.imagecache.last_refresh_was_finished';

  public function __construct(
    protected readonly StateInterface $state,
    protected readonly TimeInterface $time,
    protected readonly QueueFactory $queueFactory,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly JoinupOssCatalogueImagecacheWarmer $imagecacheWarmer,
    #[Autowire(service: 'logger.channel.joinup_oss_catalogue')]
    protected readonly LoggerChannelInterface $logger,
    protected readonly ConfigFactoryInterface $configFactory,
    protected readonly FileSystemInterface $fileSystem,
  ) {}

  /**
   * Queues files of existing OSS solutions to be refreshed.
   */
  #[Hook('cron')]
  public function cron(): void {
    $requestTime = $this->time->getRequestTime();
    $lastRefreshTimestamp = $this->state->get(static::LAST_REFRESH_TIME_KEY, 0);
    $lastRefreshWasFinished = $this->state->get(static::LAST_REFRESH_FINISHED_KEY, TRUE);

    $warmer = $this->queueFactory->get('joinup_oss_catalogue_imagecache_warmer');
    $warmer->createQueue();
    $itemCount = $warmer->numberOfItems();
    // If last refresh was just finished, we move files to another directory.
    if (!$itemCount && !$lastRefreshWasFinished) {
      $this->state->set(static::LAST_REFRESH_FINISHED_KEY, TRUE);
      $this->relocateImagecacheDir($lastRefreshTimestamp);
      return;
    }

    // OSS refresh happens at 0200AM, so we stabilize this job at 0400AM.
    $actualRefreshTimestamp = (new \DateTime('@' . $requestTime))
      ->setTime(4, 0)
      ->getTimestamp();

    if (($requestTime - $lastRefreshTimestamp) <= static::OSS_REMOTE_IMAGE_CACHE_REFRESH_INTERVAL) {
      return;
    }

    // Don't populate queue if there are still some items to process.
    if ($itemCount) {
      $this->logger->warning(
        'Image cache warmer queue still has @count item(s) to process. Image refresh will be attempted at the next cron run.',
        ['@count' => $itemCount],
      );
      return;
    }

    $ossSolutionStorage = $this->entityTypeManager->getStorage('node');
    $ossSolutionIds = $ossSolutionStorage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'oss_solution')
      // Exclude items updated in the last 4 hours.
      ->condition('changed', $requestTime - (4 * 60 * 60), '<')
      ->execute();
    $this->state->set(static::LAST_REFRESH_TIME_KEY, $actualRefreshTimestamp);
    $this->state->set(static::LAST_REFRESH_FINISHED_KEY, FALSE);

    if (!$ossSolutionIds) {
      return;
    }

    foreach ($ossSolutionStorage->loadMultiple($ossSolutionIds) as $ossSolution) {
      assert($ossSolution instanceof OssSolutionInterface);
      $this->imagecacheWarmer->queueImageUrls($ossSolution);
    }
  }

  /**
   * Moves cached external images into a new cache directory.
   *
   * Also changes Imagecache External's cache directory settings to the new dir.
   * The necessary render cache invalidation should happen automatically since
   * the related config is added to the output as cache dependency.
   *
   * @param int $lastRefreshTimestamp
   *   Timestamp when the last update was begun.
   */
  protected function relocateImagecacheDir(int $lastRefreshTimestamp): void {
    $defaultScheme = $this->configFactory->get('system.file')->get('default_scheme');
    $previousCacheDir = $this->configFactory->get('imagecache_external.settings')->get('imagecache_directory');
    if (!file_exists("$defaultScheme://$previousCacheDir")) {
      return;
    }
    // We don't want config overrides to leak into the stored configuration,
    // so we read the raw setting.
    $imagecacheSettingsEditable = $this->configFactory->getEditable('imagecache_external.settings');
    $previousCacheDirRaw = $imagecacheSettingsEditable->get('imagecache_directory') ?: '';
    $cacheDirParts = explode('-', trim($previousCacheDirRaw, ' -')) ?: ['externals'];
    if (count($cacheDirParts) > 1) {
      array_pop($cacheDirParts);
    }
    $cacheDirParts[] = $lastRefreshTimestamp;

    $imagecacheSettingsEditable->set('imagecache_directory', implode('-', $cacheDirParts))->save();
    // Move if necessary.
    $newCacheDir = $this->configFactory->get('imagecache_external.settings')->get('imagecache_directory');
    if ($previousCacheDir === $newCacheDir) {
      // Active config is the same, we don't have to do anything.
      return;
    }

    $newDirPrepared = $this->fileSystem->prepareDirectory($newCacheDir, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
    // If the new directory cannot be prepared for writing into it, restore the
    // previous setting and log a warning.
    if (!$newDirPrepared) {
      $imagecacheSettingsEditable->set('imagecache_directory', $previousCacheDirRaw)->save();
      $this->logger->warning(
        'The new Imagecache External cache directory "@new-directory" cannot be prepared. Restored the previous setting which was "@original-dir".',
        ['@new-directory' => $newCacheDir, '@original-dir' => $previousCacheDirRaw],
      );
      return;
    }

    $symfonyFs = new Filesystem();
    $symfonyFs->rename("$defaultScheme://$previousCacheDir", "$defaultScheme://$newCacheDir");
  }

}
