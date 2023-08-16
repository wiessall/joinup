<?php

declare(strict_types = 1);

namespace Drupal\joinup_oss_catalogue\Logger\Handler;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\eu_oss_catalogue\Entity\OssSolutionInterface;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Logger;

/**
 * EU OSS catalogue handler.
 *
 * @see \Drupal\monolog\Logger\Handler\DrupalMailHandler
 */
class EuOssCatalogueHandler extends AbstractProcessingHandler {

  /**
   * Constructs EU OSS catalogue handler.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity type manager.
   * @param int $level
   *   The minimum logging level at which this handler will be triggered.
   * @param bool $bubble
   *   Whether the messages that are handled can bubble up the stack or not.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    int $level = Logger::DEBUG,
    bool $bubble = TRUE
  ) {
    parent::__construct($level, $bubble);
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function write(array $record): void {
    /** @var \Drupal\message\Entity\Message $message */
    $message = $this->entityTypeManager->getStorage('message')->create([
      'template' => 'eu_oss_catalogue_log',
    ]);
    $message->set('oss_message', $record['message']);
    $message->set('oss_severity', $record['level']);

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
