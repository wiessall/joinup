<?php

declare(strict_types=1);

namespace Drupal\joinup_oss_catalogue\Plugin\QueueWorker;

use Drupal\Core\Queue\QueueWorkerBase;

/**
 * Downloads the queued remote files.
 *
 * @QueueWorker(
 *   id = "joinup_oss_catalogue_imagecache_warmer",
 *   title = @Translation("Portal OSS Catalogue remote image cache warmer"),
 *   cron = {"time" = 60},
 * )
 */
class JoinupOssCatalogueImagecacheWarmerQueue extends QueueWorkerBase {

  /**
   * {@inheritdoc}
   */
  public function processItem($data): void {
    imagecache_external_generate_path($data['source']);
  }

}
