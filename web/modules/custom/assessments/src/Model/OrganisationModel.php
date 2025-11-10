<?php

declare(strict_types=1);

namespace Drupal\assessments\Model;

/**
 * Defines the organisation model class (public_organisation).
 */
class OrganisationModel extends AssessmentModelBase implements AssessmentModelInterface {

  /**
   * {@inheritdoc}
   */
  protected function getAllowedResourceProperties(): array {
    return [
      'name',
      'description',
      'organisation_type',
      'country',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getReferencedEntityProperties(): array {
    return [];
  }

}
