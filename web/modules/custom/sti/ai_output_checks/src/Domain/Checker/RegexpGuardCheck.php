<?php

namespace Drupal\ai_output_checks\Domain\Checker;

use Drupal\ai_output_checks\Domain\ValueObject\CheckReport;
use Drupal\ai_output_checks\Domain\ValueObject\Finding;
use Drupal\ai_output_checks\Service\RegexpChecker;

final class RegexpGuardCheck implements AiCheckInterface {

  public function __construct(
    private readonly RegexpChecker $regexpChecker,
  ) {}

  public function id(): string {
    return 'regexp_guard';
  }

  public function supports(string $fieldName, string $fieldType): bool {
    // Apply to text-ish fields (and title).
    if ($fieldName === 'title') {
      return TRUE;
    }
    return in_array($fieldType, [
      'string', 'string_long', 'text', 'text_long', 'text_with_summary',
    ], TRUE);
  }

  public function check(string $fieldName, array $values): CheckReport {
    $report = new CheckReport($fieldName);

    foreach ($values as $value) {
      $hits = $this->regexpChecker->check((string) $value);
      foreach ($hits as $hit) {
        $report->add(new Finding(
          checkId: $this->id(),
          severity: 'error',
          message: 'Regex guard hit.',
          evidence: (string) ($hit['match'] ?? $value),
          data: $hit,
        ));
      }
    }

    return $report;
  }

}
