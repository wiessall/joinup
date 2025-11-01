<?php

declare(strict_types=1);

namespace Drupal\assessments\Model;

/**
 * Defines the statement model class.
 */
class StatementModel extends AssessmentModelBase implements AssessmentModelInterface {

  public function process(): void {}

  /**
   * {@inheritdoc}
   */
  protected function bundle(): string {
    return 'statement';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEntityValues(): array {
    return [
      'interpretation' => $this->data['interpretation'] ?? NULL,
      'judgement' => $this->data['judgement'] ?? NULL,
      // The API uses `type`, but field is `statement_type` in Joinup.
      'statement_type' => $this->data['statement_type'] ?? ($this->data['type'] ?? NULL),
    ];
  }

}
