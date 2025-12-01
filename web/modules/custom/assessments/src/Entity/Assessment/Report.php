<?php

declare(strict_types=1);

namespace Drupal\assessments\Entity\Assessment;

use Drupal\assessments\Entity\Assessment;
use Drupal\assessments\Entity\AssessmentResourceInterface;

/**
 * A bundle class for assessment entities.
 */
final class Report extends Assessment implements AssessmentResourceInterface {

  /**
   * {@inheritdoc}
   */
  public function getResourceAllowedProperties(): array {
    return [
      'name',
      'relevant_report',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getResourceReferenceProperties(): array {
    return [
      'binding_requirement',
      'provider',
      'results_in',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getResourceRequiredProperties(): array {
    return [
      'name',
      'binding_requirement',
      'provider',
      'results_in',
    ];
  }

}
