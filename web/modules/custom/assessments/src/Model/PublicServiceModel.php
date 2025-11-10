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

}
