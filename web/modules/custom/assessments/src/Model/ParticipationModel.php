<?php

declare(strict_types=1);

namespace Drupal\assessments\Model;

/**
 * Defines the participation model class.
 */
class ParticipationModel extends AssessmentModelBase implements AssessmentModelInterface {

  public function process(): void {}

  protected function bundle(): string {
    return 'participation';
  }

  protected function getEntityValues(): array {
    return [
      'country' => $this->data['country'] ?? NULL,
      'participation_type' => $this->data['participation_type'] ?? NULL,
    ];
  }

}
