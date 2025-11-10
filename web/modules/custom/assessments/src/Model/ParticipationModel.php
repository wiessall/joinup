<?php

declare(strict_types=1);

namespace Drupal\assessments\Model;

/**
 * Defines the participation model class.
 */
class ParticipationModel extends AssessmentModelBase implements AssessmentModelInterface {

  /**
   * {@inheritdoc}
   */
  protected function getAllowedResourceProperties(): array {
    return [
      'country',
      'participation_type',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getReferencedEntityProperties(): array {
    return [];
  }

}
