<?php

declare(strict_types=1);

namespace Drupal\eu_oss_catalogue\Validator;

/**
 * Interface for publiccode.yml validator.
 */
interface PublicCodeValidatorInterface {

  /**
   * Validates a publiccode.yml YAML string.
   *
   * @param string $yamlContent
   *   The YAML content to validate.
   *
   * @return \Drupal\eu_oss_catalogue\Validator\PublicCodeValidationResultInterface
   *   The validation result.
   */
  public function validate(string $yamlContent): PublicCodeValidationResultInterface;

}
