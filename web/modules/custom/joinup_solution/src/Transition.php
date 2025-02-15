<?php

declare(strict_types=1);

namespace Drupal\joinup_solution;

/**
 * Transition to apply on sub content of a solution if it was (un)archived.
 */
enum Transition {

  case Archive;
  case Unarchive;

  /**
   * Returns the machine name of the transition.
   *
   * @return string
   *   The machine name of the transition.
   */
  public function getMachineName(): string {
    return match ($this) {
      self::Archive => 'archive',
      self::Unarchive => 'unarchive',
    };
  }

}
