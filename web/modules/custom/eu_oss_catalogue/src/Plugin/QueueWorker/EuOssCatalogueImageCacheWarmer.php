<?php

declare(strict_types = 1);

namespace Drupal\eu_oss_catalogue\Plugin\QueueWorker;

use Drupal\Core\Queue\QueueWorkerBase;

/**
 * Downloads the queued remote files.
 *
 * @QueueWorker(
 *   id = "eu_oss_catalogue_imagecache_warmer",
 *   title = @Translation("EU OSS Catalogue remote image cache warmer"),
 *   cron = {"time" = 60},
 * )
 */
class EuOssCatalogueImageCacheWarmer extends QueueWorkerBase {

  /**
   * {@inheritdoc}
   */
  public function processItem($data): void {
    imagecache_external_generate_path($data['source']);
  }

}
