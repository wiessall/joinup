<?php

declare(strict_types = 1);

namespace Drupal\joinup_core\Logger\Handler;

use Drupal\Core\DestructableInterface;
use Monolog\Handler\TelegramBotHandler;
use Monolog\Logger;

/**
 * Wraps the TelegramBotHandler Monolog handler.
 *
 * @see \Monolog\Handler\TelegramBotHandler
 */
class TelegramHandler extends TelegramBotHandler implements DestructableInterface {

  use WrapperHandlerTrait;

  /**
   * Lock name.
   */
  private const LOCK_NAME = 'joinup.logger.telegram';

  /**
   * Collected list of records to be logged on kernel terminate.
   */
  protected array $records;

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

  /**
   * {@inheritdoc}
   */
  public function write(array $record): void {
    // Sending the message to Telegram requires to honour the API limits, such
    // as "no more than one message per second in a chat". If multiple messages
    // are generated in the life of a request, we need to sleep at least one
    // second between two consecutive API calls. That would keep the HTTP
    // request alive and will block the response to the client until all the
    // messages are broadcast. In order to avoid this, we collect all messages
    // and, after the response is sent to the client, we do the actual API
    // calls, in the kernel terminate phase.
    // @see https://core.telegram.org/bots/faq#my-bot-is-hitting-limits-how-do-i-avoid-this
    // @see \Drupal\Core\DependencyInjection\Compiler\RegisterServicesForDestructionPass()
    // @see \Drupal\Core\EventSubscriber\KernelDestructionSubscriber
    $this->records[] = $record;
  }

  /**
   * {@inheritdoc}
   */
  public function destruct(): void {
    if (!$this->records) {
      return;
    }

    // Try to acquire a lock as other requests might have also started to send
    // messages to Telegram. Twenty seconds might look too much but, in fact,
    // it's the penalty time that Telegram sets when the limits are offended.
    while (!$this->lockBackend->acquire(self::LOCK_NAME, 20.0)) {
      $this->lockBackend->wait(self::LOCK_NAME, 20);
    }
    foreach ($this->records as $delta => $record) {
      // Cannot send more than one message per second inside a particular chat.
      // @see https://core.telegram.org/bots/faq#my-bot-is-hitting-limits-how-do-i-avoid-this
      if ($delta > 0) {
        sleep(1);
      }
      parent::write($record);
    }

    $this->lockBackend->release(self::LOCK_NAME);
  }

}
