<?php

declare(strict_types=1);

namespace Drupal\assessments\Model;

/**
 * Defines the file reference model class (logical file object with URI).
 */
class FileModel extends AssessmentModelBase implements AssessmentModelInterface {

  public function process(): void {}

  protected function bundle(): string {
    return 'file';
  }

  protected function getEntityValues(): array {
    return [
      'file_uri' => $this->data['file_uri'] ?? NULL,
    ];
  }

}
