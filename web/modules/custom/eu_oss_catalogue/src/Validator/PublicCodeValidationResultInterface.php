<?php

declare(strict_types=1);

namespace Drupal\eu_oss_catalogue\Validator;

/**
 * Interface for publiccode.yml validation result.
 */
interface PublicCodeValidationResultInterface {

  /**
   * Checks if the publiccode.yml is valid.
   *
   * @return bool
   *   TRUE if valid, FALSE otherwise.
   */
  public function isValid(): bool;

  /**
   * Gets validation errors.
   *
   * @return array
   *   Array of error messages.
   */
  public function getErrors(): array;

}
