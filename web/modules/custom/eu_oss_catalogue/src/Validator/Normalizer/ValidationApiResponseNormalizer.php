<?php

declare(strict_types=1);

namespace Drupal\eu_oss_catalogue\Validator\Normalizer;

use Drupal\eu_oss_catalogue\Validator\Model\ValidationApiResponse;
use Drupal\eu_oss_catalogue\Validator\Model\ValidationIssue;
use Drupal\eu_oss_catalogue\Validator\Model\ValidationIssueType;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

/**
 * Denormalizer for the ValidationApiResponse model.
 */
class ValidationApiResponseNormalizer implements DenormalizerInterface {

  /**
   * {@inheritdoc}
   */
  public function denormalize(mixed $data, string $type, ?string $format = NULL, array $context = []): ValidationApiResponse {
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

    return new ValidationApiResponse(
      valid: $data['valid'],
      normalized: $data['normalized'] ?? NULL,
      results: $results,
    );
  }

  /**
   * {@inheritdoc}
   */
  public function supportsDenormalization(mixed $data, string $type, ?string $format = NULL, array $context = []): bool {
    return $type === ValidationApiResponse::class;
  }

  /**
   * {@inheritdoc}
   */
  public function getSupportedTypes(?string $format): array {
    return [ValidationApiResponse::class => TRUE];
  }

}
