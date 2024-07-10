<?php

declare(strict_types=1);

namespace Drupal\joinup_log\Logger\Handler;

use Drupal\Core\DestructableInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
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
   */
  protected array $records = [];

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected JoinupGroupManagerInterface $groupManager,
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

    foreach ($this->records as $record) {
    }
  }

}
