<?php

declare(strict_types=1);

namespace Drupal\joinup_oss_catalogue\Logger\Handler;

use Drupal\Core\DestructableInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Logger;

/**
 * EU OSS catalogue handler.
 *
 * @see \Drupal\monolog\Logger\Handler\DrupalMailHandler
 */
class EuOssCatalogueHandler extends AbstractProcessingHandler implements DestructableInterface {

  /**
   * Log entries collector.
   */
  protected array $records = [];

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
   */
  public function write(array $record): void {
    $this->records[] = $record;
  }

  /**
   * {@inheritdoc}
   */
  public function destruct(): void {
    foreach ($this->records as $record) {
      /** @var \Drupal\message\MessageInterface $message */
      $message = $this->entityTypeManager->getStorage('message')->create([
        'template' => 'eu_oss_catalogue_log',
        'oss_message' => [
          'value' => $record['message'],
          'format' => 'content_editor',
        ],
        'oss_severity' => $record['level'],
      ]);

      foreach (['oss_landing_url', 'oss_source', 'oss_solution'] as $item) {
        if (!isset($record['context'][$item])) {
          continue;
        }

        $message->set($item, $record['context'][$item]);
      }

      // Remove backtrace and exception as they may contain unserializable vars.
      unset($record['context']['backtrace'], $record['context']['exception']);
      $message->setArguments($record['context']);

      $message->save();
    }
  }

}
