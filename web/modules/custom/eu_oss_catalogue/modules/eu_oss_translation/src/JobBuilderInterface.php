<?php

declare(strict_types=1);

namespace Drupal\eu_oss_translation;

use Drupal\eu_oss_catalogue\Entity\OssSolutionInterface;

/**
 * Describes job_builder service.
 */
interface JobBuilderInterface {

  /**
   * Creates the tmgmt job.
   *
   * @param \Drupal\eu_oss_catalogue\Entity\OssSolutionInterface $entity
   *   OSS solution entity.
   */
  public function create(OssSolutionInterface $entity): void;

}
