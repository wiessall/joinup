<?php

declare(strict_types=1);

namespace Drupal\assessments\Model;

/**
 * Defines the assessment model interface.
 */
interface AssessmentModelInterface {

  /**
   * Returns the entity type ID.
   */
  public function getEntityTypeId(): string;

  /**
   * Returns the entity bundle.
   */
  public function getEntityBundle(): string;

  /**
   * Returns the entity values.
   */
  public function getEntityValues(): array;

  /**
   * Returns a list of referenced entity properties.
   *
   * This will be used both to navigate down to the children elements and to
   * validate invalid keys.
   */
  public function getReferencedEntityProperties(): array;

}
