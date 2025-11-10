<?php

declare(strict_types=1);

namespace Drupal\assessments;

use Drupal\assessments\Model\AssessmentModelInterface;

/**
 * @todo Add interface description.
 */
interface ResourceParserInterface {

  /**
   * Parses inbound data from a structured array.
   *
   * @param array $data
   *   The structured data to be parsed.
   * @param \Drupal\assessments\Model\AssessmentModelInterface|null $parent
   *   The parent model. Defaults to NULL for initial call.
   * @param string $type
   *   The type of the model. Defaults to 'assessment' for initial call.
   */
  public function parseInbound(array $data, ?AssessmentModelInterface $parent = NULL, string $type = 'assessment'): void;

}
