<?php

declare(strict_types=1);

namespace Drupal\joinup_core;

/**
 * Interface ConfigReadOnlyInterface
 */
interface ConfigReadOnlyInterface {

  /**
   * Checks if read-only mode is enabled.
   *
   * @return bool
   *   Returns true if read-only mode is enabled, false otherwise.
   */
  public function isReadOnlyEnabled(): bool;

  /**
   * Disables read-only mode.
   */
  public function disableReadOnly(): void;

  /**
   * Enables read-only mode.
   */
  public function enableReadOnly(): void;

}
