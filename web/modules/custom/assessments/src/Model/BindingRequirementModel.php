<?php

declare(strict_types=1);

namespace Drupal\assessments\Model;

use Drupal\assessments\Model\AssessmentModelBase;
use Drupal\assessments\Model\AssessmentModelInterface;

/**
 * Defines the binding requirement model class.
 */
class BindingRequirementModel extends AssessmentModelBase implements AssessmentModelInterface {

  /**
   * {@inheritdoc}
   */
  protected function getAllowedResourceProperties(): array {
    return [
      'documented_in',
      'name',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getReferencedEntityProperties(): array {
    return [
      'expression',
      'affects',
      'has_participation',
      'asset',
      'documents',
    ];
  }

}
