<?php

declare(strict_types=1);

namespace Drupal\assessments\Model;

/**
 * Defines the assessment model interface.
 */
interface AssessmentModelInterface {

  /**
   * Returns a list of referenced entity properties.
   *
   * This will be used both to navigate down to the children elements and to
   * validate invalid keys.
   *
   * @return array
   */
  public function getReferencedEntityProperties(): array;

}
