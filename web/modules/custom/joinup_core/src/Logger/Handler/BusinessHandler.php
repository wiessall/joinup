<?php

declare(strict_types=1);

namespace Drupal\joinup_core\Logger\Handler;

use Drupal\Core\DestructableInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Logger;

/**
 * Business handler.
 *
 * @see \Drupal\monolog\Logger\Handler\DrupalMailHandler
 */
class BusinessHandler extends AbstractProcessingHandler implements DestructableInterface {

  /**
   * Log entries collector.
   */
  protected array $records = [];

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    int $level = Logger::DEBUG,
    bool $bubble = TRUE,
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
        'template' => 'business',
        'severity' => $record['level'],
      ]);

      // Remove backtrace and exception as they may contain unserializable vars.
      unset($record['context']['backtrace'], $record['context']['exception']);
      $message->setArguments($record['context']);
      $message->save();
    }
  }

}
