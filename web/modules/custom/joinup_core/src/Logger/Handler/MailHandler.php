<?php

declare(strict_types=1);

namespace Drupal\joinup_core\Logger\Handler;

use Drupal\monolog\Logger\Handler\DrupalMailHandler;
use Monolog\Logger;

/**
 * Wraps the DrupalMailHandler Monolog handler.
 *
 * @see \Drupal\monolog\Logger\Handler\DrupalMailHandler
 */
class MailHandler extends DrupalMailHandler {

  use WrapperHandlerTrait;

  /**
   * {@inheritdoc}
   */
  public static function getDefaultSettings(): array {
    return [
      'to' => '',
      'level' => Logger::ERROR,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function isHandling(array $record): bool {
    return parent::isHandling($record)
      && !empty($this->settings['to'])
      && isset(static::getSeverityLevels()[$this->settings['level']]);
  }

}
