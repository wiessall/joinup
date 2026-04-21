<?php

declare(strict_types=1);

namespace Drupal\joinup_core\Update;

/**
 * Interface for update processes run as the backend moderator.
 */
interface UpdateProcessInterface {

  /**
   * Processes a batch step.
   *
   * @param array $sandbox
   *   The sandbox array passed by the deploy hook.
   */
  public function process(array &$sandbox): void;

}
