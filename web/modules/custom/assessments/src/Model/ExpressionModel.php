<?php

declare(strict_types=1);

namespace Drupal\assessments\Model;

/**
 * Defines the expression model class.
 */
class ExpressionModel extends AssessmentModelBase implements AssessmentModelInterface {

  /**
   * {@inheritdoc}
   */
  protected function getAllowedResourceProperties(): array {
    return [
      'name',
      'description',
      'documented_in',
      'expression_type',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getReferencedEntityProperties(): array {
    return [];
  }

}
