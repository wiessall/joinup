<?php

declare(strict_types=1);

namespace Drupal\eu_oss_catalogue\Validator;

use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * Service for validating publiccode.yml files.
 */
class PublicCodeValidator implements PublicCodeValidatorInterface {

  public function __construct(
    protected ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function validate(?string $yamlContent): PublicCodeValidationResultInterface {
    if (is_null($yamlContent)) {
      return new PublicCodeValidationResult(
        isValid: FALSE,
        errors: ['publiccode.yml is missing'],
      );
    }

    return new PublicCodeValidationResult(
      isValid: FALSE,
      errors: ['publiccode.yml validation is currently in progress and not yet available.'],
    );
  }

}
