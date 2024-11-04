<?php

declare(strict_types=1);

namespace Drupal\joinup_assessment;

/**
 * Provides an interface for assessment helper class.
 */
interface AssessmentInterface {

  /**
   * Group id.
   *
   * Space where assessments are stored.
   */
  public const string GROUP_PARENT_ID = 'http://data.europa.eu/w21/c5e99eae-6d31-47a0-b914-a4b9f4ea82cf';

}
