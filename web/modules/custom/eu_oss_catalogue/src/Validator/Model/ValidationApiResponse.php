<?php

declare(strict_types=1);

namespace Drupal\eu_oss_catalogue\Validator\Model;

/**
 * Deserialized response from the PublicCode validator API.
 *
 * @see https://github.com/publiccodenet/publiccode-parser-go
 */
readonly class ValidationApiResponse implements ValidationApiResponseInterface {

  /**
   * Constructor.
   *
   * @param bool $valid
   *   Whether the publiccode.yml passed validation.
   * @param string|null $normalized
   *   Normalized publiccode.yml content, or NULL when invalid.
   * @param \Drupal\eu_oss_catalogue\Validator\Model\ValidationIssue[] $results
   *   All validation issues returned by the API.
   */
  public function __construct(
    private bool $valid,
    private ?string $normalized,
    private array $results,
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
  public function getResults(): array {
    return $this->results;
  }

  /**
   * {@inheritdoc}
   */
  public function getResultsByType(ValidationIssueType $type): array {
    return array_values(array_filter(
      $this->results,
      static fn(ValidationIssue $issue): bool => $issue->type === $type,
    ));
  }

}
