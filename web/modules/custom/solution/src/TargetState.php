<?php

declare(strict_types = 1);

namespace Drupal\solution;

/**
 * States to change sub content of a solution when it transits to (un)archived.
 */
enum TargetState {

  case ARCHIVED;
  case PUBLISHED;

  /**
   * Returns the machine name of the target workflow state.
   *
   * @return string
   *   The machine name of the target workflow state.
   */
  public function getMachineName(): string {
    return match ($this) {
      self::ARCHIVED => 'archived',
      self::PUBLISHED => 'published',
    };
  }

}
