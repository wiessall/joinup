<?php

declare(strict_types=1);

namespace Drupal\assessments\Model;

/**
 * Defines the organisation model class (public_organisation).
 */
class OrganisationModel extends AssessmentModelBase implements AssessmentModelInterface {

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
    return 'public_organisation';
  }

  /**
   * {@inheritdoc}
   */
  public function getAllowedResourceProperties(): array {
    return [
      'name',
      'description',
      'organisation_type',
      'country',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getReferencedEntityProperties(): array {
    // @todo Return empty by default in the base class.
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getRequiredResourceProperties(): array {
    return [];
  }

}
