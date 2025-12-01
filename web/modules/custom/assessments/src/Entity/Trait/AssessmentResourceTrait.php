<?php

declare(strict_types=1);

namespace Drupal\assessments\Entity\Trait;

use Drupal\assessments\Entity\AssessmentResourceInterface;

/**
 * Defines the assessment model base class.
 */
trait AssessmentResourceTrait {

  /**
   * {@inheritdoc}
   */
  public function getResourceAllowedProperties(): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getResourceReferenceProperties(): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getResourceRequiredProperties(): array {
    return [];
  }

}
