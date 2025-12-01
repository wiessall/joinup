<?php

declare(strict_types=1);

namespace Drupal\assessments\Entity;

/**
 * Defines the assessment model interface.
 */
interface AssessmentResourceInterface {

  /**
   * Returns a list of referenced entity properties.
   *
   * This will be used both to navigate down to the children elements and to
   * validate invalid keys.
   */
  public function getResourceReferenceProperties(): array;

  /**
   * Returns a list of allowed resource incoming API properties.
   *
   * @return array{string}
   *   An array of allowed resource properties.
   */
  public function getResourceAllowedProperties(): array;

  /**
   * Returns a list of required resource incoming API properties.
   *
   * @return array{string}
   *   An array of required resource properties.
   */
  public function getResourceRequiredProperties(): array;

}
