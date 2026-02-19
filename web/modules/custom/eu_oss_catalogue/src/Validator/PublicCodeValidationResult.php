<?php

declare(strict_types=1);

namespace Drupal\eu_oss_catalogue\Validator;

/**
 * Validation result for publiccode.yml.
 */
class PublicCodeValidationResult implements PublicCodeValidationResultInterface {

  /**
   * Constructor.
   *
   * @param bool $isValid
   *   Whether the validation passed.
   * @param array $errors
   *   Array of error messages.
   */
  public function __construct(
    protected bool $isValid,
    protected array $errors = [],
  ) {}

  /**
   * {@inheritdoc}
   */
  public function isValid(): bool {
    return $this->isValid;
  }

  /**
   * {@inheritdoc}
   */
  public function getErrors(): array {
    return $this->errors;
  }

}
