<?php

declare(strict_types=1);

namespace Drupal\eu_oss_catalogue\Validator\Model;

/**
 * Interface for the PublicCode validator API response.
 */
interface ValidationApiResponseInterface {

  /**
   * Whether the publiccode.yml passed validation.
   *
   * @return bool
   *   TRUE if the file is valid, FALSE otherwise.
   */
  public function isValid(): bool;

  /**
   * Returns the normalized publiccode.yml content when valid.
   *
   * @return string|null
   *   Normalized content, or NULL when the file is invalid.
   */
  public function getNormalized(): ?string;

  /**
   * Returns all validation issues regardless of type.
   *
   * @return \Drupal\eu_oss_catalogue\Validator\Model\ValidationIssue[]
   *   All issues returned by the validator.
   */
  public function getResults(): array;

  /**
   * Returns issues filtered by the given type.
   *
   * @param \Drupal\eu_oss_catalogue\Validator\Model\ValidationIssueType $type
   *   The issue type to filter by.
   *
   * @return \Drupal\eu_oss_catalogue\Validator\Model\ValidationIssue[]
   *   Issues matching the requested type.
   */
  public function getResultsByType(ValidationIssueType $type): array;

}
