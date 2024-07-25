<?php

declare(strict_types=1);

namespace Drupal\joinup_log\Logger\Handler;

use Drupal\Core\DestructableInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LogMessageParserInterface;
use Drupal\joinup_group\JoinupGroupManagerInterface;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;

/**
 * Business handler.
 *
 * @see \Drupal\monolog\Logger\Handler\DrupalMailHandler
 */
class BusinessHandler extends AbstractProcessingHandler implements DestructableInterface {

  /**
   * Log entries collector.
   *
   * @var \Monolog\LogRecord[]
   */
  protected array $records = [];

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected JoinupGroupManagerInterface $groupManager,
    protected LogMessageParserInterface $parser,
    Level $level = Level::Debug,
    bool $bubble = TRUE,
  ) {
    parent::__construct($level, $bubble);
  }

  /**
   * {@inheritdoc}
   */
  public function write(LogRecord $record): void {
    $this->records[] = $record;
  }

  /**
   * {@inheritdoc}
   */
  public function destruct(): void {
    $group = $this->groupManager->getGroupFromContext();
    $logStorage = $this->entityTypeManager->getStorage('joinup_log');

    foreach ($this->records as $record) {
      $logStorage->create(
        [
          'group' => $group?->id(),
          'type' => $record->context['type'] ?? NULL,
          'message' => $record->message,
          'severity' => $record->level->value,
          'uid' => $record->extra['uid'],
          'actor' => $this->getActor($record),
          'location' => $record->extra['request_uri'],
          'referer' => $record->extra['referer'],
          'hostname' => $record->extra['ip'],
          'timestamp' => $record->datetime->getTimestamp(),
        ]
      )->save();
    }
  }

  /**
   * Retrieves the actor from a log record.
   *
   * @param \Monolog\LogRecord $record
   *   The log record.
   *
   * @return int|null
   *   The actor.
   */
  protected function getActor(LogRecord $record): ?int {
    $actor = $record->context['actor'] ?? NULL;
    return is_numeric($actor) ? (int) $actor : NULL;
  }

}
