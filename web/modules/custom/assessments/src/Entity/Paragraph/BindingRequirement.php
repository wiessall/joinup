<?php

declare(strict_types=1);

namespace Drupal\assessments\Entity\Paragraph;

use Drupal\assessments\Entity\AssessmentResourceInterface;
use Drupal\paragraphs\Entity\Paragraph;

/**
 * A bundle class for paragraph entities.
 */
final class BindingRequirement extends Paragraph implements AssessmentResourceInterface {

  use \Drupal\assessments\Entity\Trait\AssessmentResourceTrait;

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
      'documented_in' => [
        'field_name' => 'documented_in',
        'is_required' => FALSE,
        'is_reference' => FALSE,
      ],
      'name' => [
        'field_name' => 'name',
        'is_required' => TRUE,
        'is_reference' => FALSE,
      ],
      'expression' => [
        'field_name' => 'expression',
        'is_required' => FALSE,
        'is_reference' => TRUE,
      ],
      'affects' => [
        'field_name' => 'affects',
        'is_required' => TRUE,
        'is_reference' => TRUE,
      ],
      'has_participation' => [
        'field_name' => 'has_participation',
        'is_required' => FALSE,
        'is_reference' => TRUE,
      ],
      'asset' => [
        'field_name' => 'asset',
        'is_required' => FALSE,
        'is_reference' => TRUE,
      ],
      'documents' => [
        'field_name' => 'documents',
        'is_required' => FALSE,
        'is_reference' => TRUE,
      ],
    ];
  }

}
