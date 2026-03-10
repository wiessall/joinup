<?php

declare(strict_types=1);

namespace Drupal\eu_oss_catalogue\Model\Validation;

/**
 * Validation response value object.
 */
readonly class ValidationResponse implements ValidationResponseInterface {

  /**
   * Constructor.
   *
   * @param bool $valid
   *   Whether the project passed validation.
   * @param string|null $normalized
   *   Normalized content, or NULL when invalid.
   * @param \Drupal\eu_oss_catalogue\Model\Validation\ValidationIssue[] $issues
   *   All validation issues.
   */
  public function __construct(
    private bool $valid,
    private ?string $normalized,
    private array $issues,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function isValid(): bool {
    return $this->valid;
  }

  /**
   * {@inheritdoc}
   */
  public function getNormalized(): ?string {
    return $this->normalized;
  }

  /**
   * {@inheritdoc}
   */
  public function getIssues(): array {
    return $this->issues;
  }

  /**
   * {@inheritdoc}
   */
  public function getIssuesByType(ValidationIssueType $type): array {
    return array_values(array_filter(
      $this->issues,
      static fn(ValidationIssue $issue): bool => $issue->type === $type,
    ));
  }

}
