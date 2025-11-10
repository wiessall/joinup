<?php

declare(strict_types=1);

namespace Drupal\assessments\Model;

/**
 * Defines the asset model class.
 */
class DocumentModel extends AssessmentModelBase implements AssessmentModelInterface {

  /**
   * {@inheritdoc}
   */
  protected function getAllowedResourceProperties(): array {
    return [
      'file_uri',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getReferencedEntityProperties(): array {
    return [];
  }

}
