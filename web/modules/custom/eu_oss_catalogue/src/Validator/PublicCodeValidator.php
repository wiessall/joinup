<?php

declare(strict_types=1);

namespace Drupal\eu_oss_catalogue\Validator;

use Bfabio\PublicCodeParser\Exception\ParserException;
use Bfabio\PublicCodeParser\Exception\ValidationException;
use Bfabio\PublicCodeParser\Parser;
use Bfabio\PublicCodeParser\ParserConfig;

/**
 * Service for validating publiccode.yml files.
 *
 * Uses bfabio/publiccode-parser-php library which requires PHP FFI extension.
 *
 * @see https://github.com/bfabio/publiccode-parser-php
 */
class PublicCodeValidator implements PublicCodeValidatorInterface {

  public function __construct(
    protected bool $disableNetwork,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function validate(?string $yamlContent): PublicCodeValidationResultInterface {
    if (is_null($yamlContent)) {
      return new PublicCodeValidationResult(
        isValid: FALSE,
        errors: ['publiccode.yml is missing'],
        publicCode: NULL,
      );
    }
    try {
      // Configure parser.
      $config = new ParserConfig();
      if ($this->disableNetwork) {
        $config->setDisableNetwork(TRUE);
      }

      // Parse the YAML content.
      $parser = new Parser($config);
      $publicCode = $parser->parse($yamlContent);

      // If parsing succeeds, return a valid result.
      return new PublicCodeValidationResult(
        isValid: TRUE,
        errors: [],
        publicCode: $publicCode,
      );
    }
    catch (ValidationException $e) {
      // If validation fails, return the validation errors.
      return new PublicCodeValidationResult(
        isValid: FALSE,
        errors: $e->getErrors(),
        publicCode: NULL,
      );
    }
    catch (ParserException $e) {
      // If parsing fails, return the parser error.
      return new PublicCodeValidationResult(
        isValid: FALSE,
        errors: [$e->getMessage()],
        publicCode: NULL,
      );
    }
  }

}
