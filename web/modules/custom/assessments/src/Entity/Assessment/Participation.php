<?php

declare(strict_types=1);

namespace Drupal\assessments\Entity\Assessment;

use Drupal\assessments\Entity\Assessment;
use Drupal\assessments\Entity\Trait\AssessmentResourceTrait;

/**
 * A bundle class for assessment entities.
 */
final class Participation extends Assessment {

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
      'name' => [
        'field_name' => 'name',
        'is_required' => TRUE,
        'is_reference' => FALSE,
      ],
      'country' => [
        'field_name' => 'country',
        'is_required' => FALSE,
        'is_reference' => FALSE,
      ],
      'participation_type' => [
        'field_name' => 'participation_type',
        'is_required' => FALSE,
        'is_reference' => FALSE,
      ],
    ];
  }

}
