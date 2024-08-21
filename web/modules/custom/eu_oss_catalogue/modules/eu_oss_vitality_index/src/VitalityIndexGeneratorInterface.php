<?php

declare(strict_types=1);

namespace Drupal\eu_oss_vitality_index;

use Drupal\eu_oss_catalogue\Entity\OssSolutionInterface;

/**
 * Vitality index generator service.
 */
interface VitalityIndexGeneratorInterface {

  /**
   * Computes and stores the vitality index for one solution.
   *
   * @param \Drupal\eu_oss_catalogue\Entity\OssSolutionInterface $solution
   *   The OSS Solution entity.
   */
  public function processSolution(OssSolutionInterface $solution): void;

}
