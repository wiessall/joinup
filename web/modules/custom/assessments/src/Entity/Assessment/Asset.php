<?php

declare(strict_types=1);

namespace Drupal\assessments\Entity\Assessment;

use Drupal\assessments\Entity\Assessment;
use Drupal\assessments\Entity\AssessmentResourceInterface;
use Drupal\assessments\Entity\Trait\AssessmentResourceTrait;

/**
 * A bundle class for assessment entities.
 */
final class Asset extends Assessment implements AssessmentResourceInterface {

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
      'description' => [
        'field_name' => 'description',
        'is_required' => TRUE,
        'is_reference' => FALSE,
      ],
      'landing_page' => [
        'field_name' => 'landing_page',
        'is_required' => FALSE,
        'is_reference' => FALSE,
      ],
    ];
  }

}
