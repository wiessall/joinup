<?php

declare(strict_types=1);

namespace Drupal\eu_oss_catalogue\Model\Validation;

/**
 * Interface for a validation response.
 */
interface ValidationResponseInterface {

  /**
   * Whether the project passed validation.
   *
   * @return bool
   *   TRUE if valid, FALSE otherwise.
   */
  public function isValid(): bool;

  /**
   * Returns the normalized content when valid.
   *
   * @return string|null
   *   Normalized content, or NULL when invalid.
   */
  public function getNormalized(): ?string;

  /**
   * Returns all validation issues regardless of type.
   *
   * @return \Drupal\eu_oss_catalogue\Model\Validation\ValidationIssue[]
   *   All validation issues.
   */
  public function getIssues(): array;

  /**
   * Returns issues filtered by the given type.
   *
   * @param \Drupal\eu_oss_catalogue\Model\Validation\ValidationIssueType $type
   *   The issue type to filter by.
   *
   * @return \Drupal\eu_oss_catalogue\Model\Validation\ValidationIssue[]
   *   Issues matching the requested type.
   */
  public function getIssuesByType(ValidationIssueType $type): array;

}
