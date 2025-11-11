<?php

declare(strict_types=1);

namespace Drupal\eu_oss_catalogue\Validator;

use Bfabio\PublicCodeParser\PublicCodeV0;

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

  /**
   * Gets the parsed PublicCode object.
   *
   * @return \Bfabio\PublicCodeParser\PublicCodeV0|null
   *   The PublicCode object if valid, NULL otherwise.
   */
  public function getPublicCode(): ?PublicCodeV0;

}
