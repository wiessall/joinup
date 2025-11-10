<?php

declare(strict_types=1);

namespace Drupal\joinup_core\Logger\Handler;

use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\State\StateInterface;
use Monolog\Level;

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

  public function __construct(
    string $type,
    StateInterface $state,
    protected readonly ?LockBackendInterface $lockBackend = NULL,
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
    $levels = [];
    foreach (Level::cases() as $level) {
      $levels[$level->value] = $level->getName();
    }
    return $levels;
  }

}
