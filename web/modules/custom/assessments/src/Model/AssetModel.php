<?php

declare(strict_types=1);

namespace Drupal\assessments\Model;

/**
 * Defines the asset model class.
 */
class AssetModel extends AssessmentModelBase implements AssessmentModelInterface {

  /**
   * {@inheritdoc}
   */
  protected function getAllowedResourceProperties(): array {
    return [
      'name',
      'landing_page',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getReferencedEntityProperties(): array {
    return [];
  }

}
