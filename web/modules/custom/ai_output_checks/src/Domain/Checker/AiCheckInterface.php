<?php

namespace Drupal\ai_output_checks\Domain\Checker;

use Drupal\ai_output_checks\Domain\ValueObject\CheckReport;

interface AiCheckInterface {
  public function id(): string;

  /**
   * Keep it simple for Phase 1: fieldName + fieldType.
   * Later we’ll replace with FieldContext.
   */
  public function supports(string $fieldName, string $fieldType): bool;

  /**
   * @param string[] $values
   */
  public function check(string $fieldName, array $values): CheckReport;
}
