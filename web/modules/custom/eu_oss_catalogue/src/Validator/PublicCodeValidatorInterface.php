<?php

declare(strict_types=1);

namespace Drupal\eu_oss_catalogue\Validator;

use Drupal\eu_oss_catalogue\Model\Validation\ValidationResponseInterface;

/**
 * Interface for publiccode.yml validator.
 */
interface PublicCodeValidatorInterface {

  /**
   * The API path used for validation requests.
   */
  const string VALIDATE_PATH = '/v1/validate';

  /**
   * Validates a publiccode.yml YAML string via the PublicCode validator API.
   *
   * @param string|null $yamlContent
   *   The YAML content to validate.
   *
   * @return \Drupal\eu_oss_catalogue\Model\Validation\ValidationResponseInterface
   *   The deserialized API response.
   *
   * @throws \RuntimeException
   *   When the API request fails.
   */
  public function validate(?string $yamlContent): ValidationResponseInterface;

}
