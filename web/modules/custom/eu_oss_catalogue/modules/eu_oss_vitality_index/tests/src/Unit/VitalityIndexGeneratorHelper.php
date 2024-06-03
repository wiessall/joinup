<?php

declare(strict_types=1);

namespace Drupal\Tests\eu_oss_vitality_index\Unit;

use Drupal\eu_oss_catalogue\Entity\OssSolutionInterface;
use Drupal\eu_oss_vitality_index\VitalityIndexGenerator;

/**
 * Extends Vitality Index Generator.
 */
class VitalityIndexGeneratorHelper extends VitalityIndexGenerator {

  /**
   * Index per day.
   */
  const array INDEX_PER_DAY = [42, 39, 23, 43, 64];

  /**
   * {@inheritdoc}
   */
  protected function getVitalityIndexPerDay(OssSolutionInterface $solution): ?array {
    return self::INDEX_PER_DAY;
  }

}
