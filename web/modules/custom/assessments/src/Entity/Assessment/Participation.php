<?php

declare(strict_types=1);

namespace Drupal\assessments\Entity\Assessment;

use Drupal\assessments\Entity\Assessment;
use Drupal\assessments\Entity\Trait\AssessmentResourceTrait;

/**
 * A bundle class for assessment entities.
 */
final class Participation extends Assessment {

  use AssessmentResourceTrait;

  /**
   * {@inheritdoc}
   */
  public function getResourceAllowedProperties(): array {
    return [
      'name',
      'country',
      'participation_type',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getResourceRequiredProperties(): array {
    return [
      'name',
    ];
  }

}
