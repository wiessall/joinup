<?php

declare(strict_types=1);

namespace Drupal\eu_oss_catalogue\Validator\Model;

/**
 * Value object representing a single issue from the PublicCode validator API.
 */
readonly class ValidationIssue {

  /**
   * Constructor.
   *
   * @param string $key
   *   The publiccode.yml field key that triggered the issue.
   * @param string $description
   *   Human-readable description of the issue.
   * @param int $line
   *   Line number where the issue was found.
   * @param int $column
   *   Column number where the issue was found.
   * @param \Drupal\eu_oss_catalogue\Validator\Model\ValidationIssueType $type
   *   The severity type of the issue.
   */
  public function __construct(
    public string $key,
    public string $description,
    public int $line,
    public int $column,
    public ValidationIssueType $type,
  ) {}

}
