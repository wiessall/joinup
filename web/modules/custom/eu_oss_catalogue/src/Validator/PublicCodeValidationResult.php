<?php

declare(strict_types=1);

namespace Drupal\eu_oss_catalogue\Validator;

use Bfabio\PublicCodeParser\PublicCodeV0;

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
   * @param \Bfabio\PublicCodeParser\PublicCodeV0|null $publicCode
   *   The parsed PublicCode object if valid.
   */
  public function __construct(
    protected bool $isValid,
    protected array $errors = [],
    protected ?PublicCodeV0 $publicCode = NULL,
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

  /**
   * {@inheritdoc}
   */
  public function getPublicCode(): ?PublicCodeV0 {
    return $this->publicCode;
  }

}
