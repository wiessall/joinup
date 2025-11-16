<?php

declare(strict_types=1);

namespace Drupal\assessments\Model;

/**
 * Defines the public service model class.
 */
class PublicServiceModel extends AssessmentModelBase implements AssessmentModelInterface {

  /**
   * {@inheritdoc}
   */
  public function getEntityTypeId(): string {
    return 'assessment';
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityBundle(): string {
    return 'public_service';
  }

  /**
   * {@inheritdoc}
   */
  protected function getAllowedResourceProperties(): array {
    return [
      'description',
      'spatial',
      'identifier',
      'name',
      'thematic_area',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getReferencedEntityProperties(): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getRequiredResourceProperties(): array {
    return [
      'description',
      'identifier',
      'name',
    ];
  }

}
