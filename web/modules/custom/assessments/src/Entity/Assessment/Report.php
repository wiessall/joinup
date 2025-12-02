<?php

declare(strict_types=1);

namespace Drupal\assessments\Entity\Assessment;

use Drupal\assessments\Entity\Assessment;
use Drupal\assessments\Entity\AssessmentResourceInterface;
use Drupal\assessments\Entity\Paragraph\BindingRequirement;
use Drupal\assessments\Entity\Trait\AssessmentResourceTrait;

/**
 * A bundle class for assessment entities.
 */
final class Report extends Assessment implements AssessmentResourceInterface {

  use AssessmentResourceTrait;

  /**
   * {@inheritdoc}
   */
  public function getResourcePropertyMappings(): array {
    return [
      'name' => [
        'field_name' => 'name',
        'is_required' => TRUE,
        'is_reference' => FALSE,
      ],
      'relevant_report' => [
        'field_name' => 'relevant_report',
        'is_required' => FALSE,
        'is_reference' => FALSE,
      ],
      'binding_requirement' => [
        'field_name' => 'binding_requirement',
        'is_required' => TRUE,
        'is_reference' => TRUE,
      ],
      'provider' => [
        'field_name' => 'provider',
        'is_required' => TRUE,
        'is_reference' => TRUE,
      ],
      'results_in' => [
        'field_name' => 'results_in',
        'is_required' => TRUE,
        'is_reference' => TRUE,
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function translateResourceValue(string $fieldName, mixed $values): mixed {
    switch ($fieldName) {
      case 'binding_requirement':
        // Binding requirements is a revision reference field, so we need to
        // further get the revision ID.
        $entities = BindingRequirement::loadMultiple($values);
        return array_values(array_map(fn($entity) => [
          'target_id' => $entity->id(),
          'target_revision_id' => $entity->getRevisionId(),
        ], $entities));
      default:
        return $values;
    }
  }

}
