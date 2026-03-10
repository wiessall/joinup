<?php

declare(strict_types=1);

namespace Drupal\eu_oss_catalogue\Validator\Normalizer;

use Drupal\eu_oss_catalogue\Model\Validation\ValidationIssue;
use Drupal\eu_oss_catalogue\Model\Validation\ValidationIssueType;
use Drupal\eu_oss_catalogue\Model\Validation\ValidationResponse;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

/**
 * Denormalizer for the ValidationResponse model.
 */
class ValidationApiResponseNormalizer implements DenormalizerInterface {

  /**
   * {@inheritdoc}
   */
  public function denormalize(mixed $data, string $type, ?string $format = NULL, array $context = []): ValidationResponse {
    $results = [];
    foreach ($data['results'] ?? [] as $issue) {
      $results[] = new ValidationIssue(
        key: $issue['key'],
        description: $issue['description'],
        line: $issue['line'],
        column: $issue['column'],
        type: ValidationIssueType::from($issue['type']),
      );
    }

    return new ValidationResponse(
      valid: $data['valid'],
      normalized: $data['normalized'] ?? NULL,
      issues: $results,
    );
  }

  /**
   * {@inheritdoc}
   */
  public function supportsDenormalization(mixed $data, string $type, ?string $format = NULL, array $context = []): bool {
    return $type === ValidationResponse::class;
  }

  /**
   * {@inheritdoc}
   */
  public function getSupportedTypes(?string $format): array {
    return [ValidationResponse::class => TRUE];
  }

}
