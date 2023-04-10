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
      'api_key' => '',
      'chat_id' => '',
      'level' => Logger::WARNING,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function isHandling(array $record): bool {
    return parent::isHandling($record) && !empty($this->settings['api_key']) && !empty($this->settings['chat_id']) && isset(static::getSeverityLevels()[$this->settings['level']]);
  }

}
