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
  public function getResourcePropertyMappings(): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getResourceAllowedProperties(): array {
    return array_keys($this->getResourcePropertyMappings());
  }

  /**
   * {@inheritdoc}
   */
  public function getResourceReferenceProperties(): array {
    $mappings = $this->getResourcePropertyMappings();
    return array_values(array_keys(array_filter($mappings, static fn(array $def): bool => !empty($def['is_reference']))));
  }

  /**
   * {@inheritdoc}
   */
  public function getResourceRequiredProperties(): array {
    $mappings = $this->getResourcePropertyMappings();
    return array_values(array_keys(array_filter($mappings, static fn(array $def): bool => !empty($def['is_required']))));
  }

}
