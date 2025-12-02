<?php

declare(strict_types=1);

namespace Drupal\assessments\Entity\Assessment;

use Drupal\assessments\Entity\Assessment;
use Drupal\assessments\Entity\AssessmentResourceInterface;
use Drupal\assessments\Entity\Trait\AssessmentResourceTrait;

/**
 * A bundle class for assessment entities.
 */
final class PublicService extends Assessment implements AssessmentResourceInterface {

  use AssessmentResourceTrait;

  /**
   * {@inheritdoc}
   */
  public function getResourcePropertyMappings(): array {
    return [
      'id' => [
        'field_name' => 'id',
        'is_required' => FALSE,
        'is_reference' => FALSE,
      ],
      'description' => [
        'field_name' => 'description',
        'is_required' => TRUE,
        'is_reference' => FALSE,
      ],
      'spatial' => [
        'field_name' => 'spatial',
        'is_required' => FALSE,
        'is_reference' => FALSE,
      ],
      'identifier' => [
        'field_name' => 'identifier',
        'is_required' => TRUE,
        'is_reference' => FALSE,
      ],
      'name' => [
        'field_name' => 'name',
        'is_required' => TRUE,
        'is_reference' => FALSE,
      ],
      'thematic_area' => [
        'field_name' => 'thematic_area',
        'is_required' => FALSE,
        'is_reference' => FALSE,
      ],
    ];
  }

}
