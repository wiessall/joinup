<?php

declare(strict_types=1);

namespace Drupal\eu_oss_catalogue\Model\Validation;

/**
 * Value object representing a single validation issue.
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
   * @param \Drupal\eu_oss_catalogue\Model\Validation\ValidationIssueType $type
   *   The severity type of the issue.
   */
  public function __construct(
    public string $key,
    public string $description,
    public int $line,
    public int $column,
    public ValidationIssueType $type,
  ) {}

  /**
   * Returns a string representation of the issue.
   *
   * @return string
   *   The issue formatted as "[key line:N column:N] description".
   */
  public function __toString(): string {
    return sprintf('[%s line:%d column:%d] %s', $this->key, $this->line, $this->column, $this->description);
  }

}
