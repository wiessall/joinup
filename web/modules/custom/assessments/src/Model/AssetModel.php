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
  public function getEntityBundle(): string {
    return 'asset';
  }

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

  /**
   * {@inheritdoc}
   */
  public function getRequiredResourceProperties(): array {
    return [
      'name',
      'description',
    ];
  }

}
