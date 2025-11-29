<?php

declare(strict_types=1);

namespace Drupal\assessments\Model;

/**
 * Defines the assessment model class.
 */
class AssessmentModel extends AssessmentModelBase implements AssessmentModelInterface {

  /**
   * {@inheritdoc}
   */
  public function getEntityTypeId(): string {
    return 'assessment';
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityBundle(array $context = []): string {
    return 'assessment';
  }

  /**
   * {@inheritdoc}
   */
  public function getAllowedResourceProperties(): array {
    return [
      // @todo Probably we need to have it as $fieldName => $property.
      'name',
      'relevant_report',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getReferencedEntityProperties(): array {
    return [
      'binding_requirement',
      'provider',
      'results_in',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getRequiredResourceProperties(): array {
    return [
      'name',
      'binding_requirement',
      'provider',
      'results_in',
    ];
  }

}
