<?php

declare(strict_types=1);

namespace Drupal\assessments\Model;

use Drupal\assessments\Model\AssessmentModelBase;
use Drupal\assessments\Model\AssessmentModelInterface;

/**
 * Defines the binding requirement model class.
 */
class BindingRequirementModel extends AssessmentModelBase implements AssessmentModelInterface {

  public function process(): void {}

  /**
   * {@inheritdoc}
   */
  protected function bundle(): string {
    return 'binding_requirement';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEntityValues(): array {
    return [
      'name' => $this->data['name'] ?? NULL,
      'documented_in' => $this->data['documented_in'] ?? NULL,
    ];
  }

}
