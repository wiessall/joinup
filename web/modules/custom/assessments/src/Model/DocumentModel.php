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
  public function getEntityTypeId(): string {
    return 'assessment';
  }

  /**
   * {@inheritdoc}
   */
  public function getEntityBundle(array $context = []): string {
    return 'document';
  }

  /**
   * {@inheritdoc}
   */
  public function getAllowedResourceProperties(): array {
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

  /**
   * {@inheritdoc}
   */
  public function getRequiredResourceProperties(): array {
    return [
      'file_uri',
    ];
  }

}
