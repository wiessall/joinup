<?php

declare(strict_types=1);

namespace Drupal\assessments\Model;

/**
 * Defines the assessment model interface.
 */
interface AssessmentModelInterface {

  /**
   * Processes the data of the entity.
   *
   * This should also call the processing of the child entities.
   */
  public function process(): void;

  /**
   * Validates the data of the entity.
   *
   * @return array
   *   A list of validation errors.
   */
  public function validate(): array;

  /**
   * Saves the entity.
   *
   * This should also call the saving of the child entities first.
   */
  public function save(): void;

}
