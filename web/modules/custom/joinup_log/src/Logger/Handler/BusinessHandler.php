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
   * @var LogRecord[]
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
      $logMessage = $record->message;
      $logContext = $record->context;
      // Remove backtrace and exception since they may contain an unserializable variable.
      unset($logContext['backtrace'], $logContext['exception']);

      $messagePlaceholders = $this->parser->parseMessagePlaceholders($logMessage, $logContext);

      $logStorage->create(
        [
          'group_entity' => $group?->id(),
          'type' => mb_substr($record->channel, 0, 64),
          'message' => $logMessage,
          'variables' => serialize($messagePlaceholders),
          'severity' => $record->level->value,
          'uid' => $record->extra['uid'],
          'location' => $record->extra['request_uri'],
          'referer' => $record->extra['referer'],
          'hostname' => mb_substr($record->extra['ip'], 0, 128),
          'timestamp' => $record->datetime->getTimestamp(),
        ]
      )->save();
    }
  }

}
