<?php

declare(strict_types=1);

namespace Drupal\assessments\Model;

/**
 * Defines the public service model class.
 */
class PublicServiceModel extends AssessmentModelBase implements AssessmentModelInterface {

  public function process(): void {}

  protected function bundle(): string {
    return 'public_service';
  }

  protected function getEntityValues(): array {
    return [
      'name' => $this->data['name'] ?? NULL,
      'thematic_area' => $this->data['thematic_area'] ?? NULL,
    ];
  }

}
