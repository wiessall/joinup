<?php

declare(strict_types=1);

namespace Drupal\assessments\Model;

/**
 * Defines the binding requirement model class.
 */
class BindingRequirementModel extends AssessmentModelBase implements AssessmentModelInterface {

  /**
   * {@inheritdoc}
   */
  public function getEntityTypeId(): string {
    return 'paragraph';
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityBundle(array $context = []): string {
    return 'binding_requirement';
  }

  /**
   * {@inheritdoc}
   */
  public function getAllowedResourceProperties(): array {
    return [
      'documented_in',
      'name',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getReferencedEntityProperties(): array {
    return [
      'expression',
      'affects',
      'has_participation',
      'asset',
      'documents',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getRequiredResourceProperties(): array {
    return [
      'name',
      'affects',
    ];
  }

}
