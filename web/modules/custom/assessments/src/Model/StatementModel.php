<?php

declare(strict_types=1);

namespace Drupal\assessments\Model;

/**
 * Defines the statement model class.
 */
class StatementModel extends AssessmentModelBase implements AssessmentModelInterface {

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
    return 'statement';
  }

  /**
   * {@inheritdoc}
   */
  protected function getAllowedResourceProperties(): array {
    return [
      'interpretation',
      'judgement',
      'type',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getReferencedEntityProperties(): array {
    return ['documents'];
  }

  /**
   * {@inheritdoc}
   */
  public function getRequiredResourceProperties(): array {
    return [
      'interpretation',
      'judgement',
      'type',
    ];
  }

}
