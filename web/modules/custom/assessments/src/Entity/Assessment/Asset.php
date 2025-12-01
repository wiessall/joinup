<?php

declare(strict_types=1);

namespace Drupal\assessments\Entity\Assessment;

use Drupal\assessments\Entity\Assessment;
use Drupal\assessments\Entity\AssessmentResourceInterface;
use Drupal\assessments\Entity\Trait\AssessmentResourceTrait;

/**
 * A bundle class for assessment entities.
 */
final class Asset extends Assessment implements AssessmentResourceInterface {

  use AssessmentResourceTrait;

  /**
   * {@inheritdoc}
   */
  public function getResourceAllowedProperties(): array {
    return [
      'name',
      'landing_page',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getResourceRequiredProperties(): array {
    return [
      'name',
      'description',
    ];
  }

}
