<?php

declare(strict_types=1);

namespace Drupal\joinup_core;

/**
 * Switcher interface.
 */
interface JoinupSwitcherInterface {

  /**
   * Checks if the passed functionality is enabled.
   *
   * @param string $feature
   *   The feature to be checked. E.g. 'config-readonly'.
   *
   * @return bool
   *   Returns true if read-only mode is enabled, false otherwise.
   */
  public function isEnabled(string $feature): bool;

  /**
   * Disables the feature mode.
   *
   * @param string $feature
   *   The feature to be disabled. E.g. 'config-readonly'.
   */
  public function disable(string $feature): void;

  /**
   * Enables read-only mode.
   *
   * @param string $feature
   *   The feature to be disabled. E.g. 'config-readonly'.
   */
  public function enable(string $feature): void;

}
