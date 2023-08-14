<?php

declare(strict_types = 1);

namespace Drupal\joinup_oss_catalogue\Logger\Handler;

use Drupal\eu_oss_catalogue\Entity\OssSolutionInterface;
use Drupal\message\Entity\Message;
use Monolog\Handler\AbstractProcessingHandler;

/**
 * EU OSS catalogue handler.
 *
 * @see \Drupal\monolog\Logger\Handler\DrupalMailHandler
 */
class EuOssCatalogueHandler extends AbstractProcessingHandler {

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function write(array $record): void {
    $message = Message::create(['template' => 'eu_oss_catalogue_log']);
    $message->set('log_message', $record['message']);
    $message->set('log_severity', $record['level']);

    if (isset($record['context']['oss_solution']) && $record['context']['oss_solution'] instanceof OssSolutionInterface) {
      $message->set('oss_solution', [$record['context']['oss_solution']->id()]);
    }

    foreach (['oss_landing_url', 'oss_source'] as $item) {
      if (!isset($record['context'][$item])) {
        continue;
      }

      $message->set($item, $record['context'][$item]);
    }

    $message->save();
  }

}
