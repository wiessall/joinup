<?php

declare(strict_types=1);

namespace Drupal\assessments\Entity\Paragraph;

use Drupal\assessments\Entity\AssessmentResourceInterface;
use Drupal\paragraphs\Entity\Paragraph;

/**
 * A bundle class for paragraph entities.
 */
final class BindingRequirement extends Paragraph implements AssessmentResourceInterface {

  /**
   * {@inheritdoc}
   */
  public function getResourceAllowedProperties(): array {
    return [
      'documented_in',
      'name',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getResourceReferenceProperties(): array {
    return [
      'expression',
      'affects',
      'has_participation',
      'asset',
      'documents',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getResourceRequiredProperties(): array {
    return [
      'name',
      'affects',
    ];
  }

}
