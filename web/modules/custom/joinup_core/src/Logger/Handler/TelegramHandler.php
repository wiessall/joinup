<?php

declare(strict_types = 1);

namespace Drupal\joinup_core\Logger\Handler;

use Monolog\Handler\TelegramBotHandler;
use Monolog\Logger;

/**
 * Wraps the TelegramBotHandler Monolog handler.
 *
 * @see \Monolog\Handler\TelegramBotHandler
 */
class TelegramHandler extends TelegramBotHandler {

  use WrapperHandlerTrait;

  /**
   * {@inheritdoc}
   */
  public static function getDefaultSettings(): array {
    return [
      'apiKey' => '',
      'channel' => '',
      'level' => Logger::ERROR,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function isHandling(array $record): bool {
    return parent::isHandling($record)
      && !empty($this->settings['apiKey'])
      && !empty($this->settings['channel'])
      && isset(static::getSeverityLevels()[$this->settings['level']]);
  }

}
