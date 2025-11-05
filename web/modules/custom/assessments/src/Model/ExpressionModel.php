<?php

declare(strict_types=1);

namespace Drupal\assessments\Model;

/**
 * Defines the expression model class.
 */
class ExpressionModel extends AssessmentModelBase implements AssessmentModelInterface {

  public function process(): void {}

  protected function bundle(): string {
    return 'expression';
  }

  protected function getEntityValues(): array {
    return [
      'name' => $this->data['name'] ?? NULL,
      'description' => $this->data['description'] ?? NULL,
      // Accept both keys from API/README.
      'documented_in' => $this->data['documented_in'] ?? ($this->data['document_in'] ?? NULL),
      'expression_type' => $this->data['expression_type'] ?? NULL,
    ];
  }

}
