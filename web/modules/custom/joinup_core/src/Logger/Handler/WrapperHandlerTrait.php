<?php

declare(strict_types=1);

namespace Drupal\joinup_core\Logger\Handler;

use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\State\StateInterface;
use Monolog\Logger;

/**
 * Generic handler wrapping mail and Telegram Monolog handlers.
 */
trait WrapperHandlerTrait {

  /**
   * Handler settings.
   *
   * @array
   */
  protected array $settings;

  /**
   * Constructs a new Monolog handler instance.
   *
   * @param string $type
   *   The type of handler, 'mail' or 'telegram'.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   * @param \Drupal\Core\Lock\LockBackendInterface|null $lockBackend
   *   The lock backend service.
   */
  public function __construct(
    string $type,
    StateInterface $state,
    protected ?LockBackendInterface $lockBackend = NULL,
  ) {
    $this->settings = $state->get('joinup.log_alerts', [$type => []])[$type] + static::getDefaultSettings();
    parent::__construct(...$this->settings);
  }

  /**
   * Provides the default handler settings.
   *
   * @return array
   *   Default handler settings.
   */
  abstract public static function getDefaultSettings(): array;

  /**
   * Returns a list of logger severity levels.
   *
   * @return string[]
   *   A list of logger severity levels.
   */
  public static function getSeverityLevels(): array {
    return [
      Logger::DEBUG => 'DEBUG',
      Logger::INFO => 'INFO',
      Logger::NOTICE => 'NOTICE',
      Logger::WARNING => 'WARNING',
      Logger::ERROR => 'ERROR',
      Logger::CRITICAL => 'CRITICAL',
      Logger::ALERT => 'ALERT',
      Logger::EMERGENCY => 'EMERGENCY',
    ];
  }

}
