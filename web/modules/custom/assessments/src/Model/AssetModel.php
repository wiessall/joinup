<?php

declare(strict_types=1);

namespace Drupal\assessments\Model;

/**
 * Defines the asset model class.
 */
class AssetModel extends AssessmentModelBase implements AssessmentModelInterface {

  public function process(): void {}

  protected function bundle(): string {
    return 'asset';
  }

  protected function getEntityValues(): array {
    return [
      'name' => $this->data['name'] ?? NULL,
      'landing_page' => $this->data['landing_page'] ?? NULL,
    ];
  }

}
