<?php

declare(strict_types=1);

namespace Drupal\assessments\Entity\Trait;

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

  /**
   * {@inheritdoc}
   */
  public function translateResourceValue(string $fieldName, mixed $values): mixed {
    // Default identity translation. Classes may override and specialize per
    // field using a switch on $fieldName.
    return $values;
  }

  /**
   * {@inheritdoc}
   */
  public function resourceEntityPresave(): void {
    // Default no-op. Classes may override to implement custom logic.
  }

}
