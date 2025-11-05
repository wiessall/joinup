<?php

declare(strict_types=1);

namespace Drupal\assessments\Model;

/**
 * Defines the organisation model class (public_organisation).
 */
class OrganisationModel extends AssessmentModelBase implements AssessmentModelInterface {

  public function process(): void {}

  protected function bundle(): string {
    return 'public_organisation';
  }

  protected function getEntityValues(): array {
    return [
      'name' => $this->data['name'] ?? NULL,
      // API uses organisation_type; field is organization_type in Joinup.
      'organization_type' => $this->data['organisation_type'] ?? ($this->data['organization_type'] ?? NULL),
      'country' => $this->data['country'] ?? NULL,
    ];
  }

}
