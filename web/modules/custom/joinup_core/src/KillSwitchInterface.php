<?php

declare(strict_types=1);

namespace Drupal\joinup_core;

/**
 * Kill switch.
 */
interface KillSwitchInterface {

  /**
   * Checks if the switch exists.
   *
   * @param string $type
   *   Type.
   *
   * @return bool
   *   Returns true if the switch exists, false otherwise.
   */
  public function exists(string $type): bool;

  /**
   * Create switch.
   *
   * @param string $type
   *   Type.
   */
  public function create(string $type): void;

  /**
   * Remove switch.
   *
   * @param string $type
   *   Type.
   */
  public function remove(string $type): void;

}
