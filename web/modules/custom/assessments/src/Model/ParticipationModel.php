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
  public function getEntityBundle(): string {
    return 'participation';
  }

  /**
   * {@inheritdoc}
   */
  protected function getAllowedResourceProperties(): array {
    return [
      'name',
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

  /**
   * {@inheritdoc}
   */
  public function getRequiredResourceProperties(): array {
    return [
      'name',
    ];
  }

}
