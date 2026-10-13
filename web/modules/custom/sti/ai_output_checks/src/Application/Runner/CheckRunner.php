<?php

namespace Drupal\ai_output_checks\Application\Runner;

use Drupal\ai_output_checks\Domain\Checker\AiCheckInterface;
use Drupal\ai_output_checks\Domain\ValueObject\CheckReport;

final class CheckRunner {

  /**
   * @param iterable<AiCheckInterface> $checks
   */
  public function __construct(
    private readonly iterable $checks,
  ) {}

  /**
   * Runs all supported checks and returns a merged report.
   *
   * @param string[] $values
   */
  public function run(string $fieldName, string $fieldType, array $values): CheckReport {
    $merged = new CheckReport($fieldName);

    foreach ($this->checks as $check) {
      if (!$check->supports($fieldName, $fieldType)) {
        continue;
      }
      $report = $check->check($fieldName, $values);
      foreach ($report->findings() as $finding) {
        $merged->add($finding);
      }
    }

    return $merged;
  }

}
