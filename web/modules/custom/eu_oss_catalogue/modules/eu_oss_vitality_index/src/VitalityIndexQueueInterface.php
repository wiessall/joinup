<?php

declare(strict_types=1);

namespace Drupal\eu_oss_vitality_index;

/**
 * Provides an interface for Vitality index queue service.
 */
interface VitalityIndexQueueInterface {

  /**
   * Queues solution.
   *
   * @param int $solutionId
   *   The OSS Solution entity id.
   *
   * @return void
   */
  public function addToQueue(int $solutionId): void;

}
