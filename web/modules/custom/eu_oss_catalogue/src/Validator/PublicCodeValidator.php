<?php

declare(strict_types=1);

namespace Drupal\eu_oss_catalogue\Validator;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\eu_oss_catalogue\Validator\Model\ValidationApiResponse;
use Drupal\eu_oss_catalogue\Validator\Model\ValidationApiResponseInterface;
use Drupal\eu_oss_catalogue\Validator\Model\ValidationIssue;
use Drupal\eu_oss_catalogue\Validator\Model\ValidationIssueType;
use Drupal\eu_oss_catalogue\Validator\Normalizer\ValidationApiResponseNormalizer;
use GuzzleHttp\ClientInterface;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Serializer;

/**
 * Service for validating publiccode.yml files via the PublicCode validator API.
 */
readonly class PublicCodeValidator implements PublicCodeValidatorInterface {

  /**
   * Serializer for deserializing API responses.
   *
   * @var \Symfony\Component\Serializer\Serializer
   */
  private Serializer $localSerializer;

  public function __construct(
    protected ConfigFactoryInterface $configFactory,
    protected ClientInterface $httpClient,
  ) {
    $this->localSerializer = new Serializer(
      [new ValidationApiResponseNormalizer()],
      [new JsonEncoder()],
    );
  }

  /**
   * {@inheritdoc}
   */
  public function validate(?string $yamlContent): ValidationApiResponseInterface {
    if ($yamlContent === NULL) {
      return $this->missingYamlResponse();
    }

    $apiUrl = $this->configFactory
      ->get('eu_oss_catalogue.settings')
      ->get('publiccode_api_url');

    try {
      $response = $this->httpClient->request('QUERY', $apiUrl . '/v1/validate', [
        'headers' => ['Content-Type' => 'application/yaml'],
        'body' => $yamlContent,
      ]);
    }
    catch (\Exception $e) {
      throw new \RuntimeException(
        sprintf('PublicCode validator API request failed: %s', $e->getMessage()),
        0,
        $e,
      );
    }

    return $this->localSerializer->deserialize(
      (string) $response->getBody(),
      ValidationApiResponse::class,
      'json',
    );
  }

  /**
   * Returns an invalid response indicating the publiccode.yml file is missing.
   *
   * @return \Drupal\eu_oss_catalogue\Validator\Model\ValidationApiResponseInterface
   *   An invalid response with a single error issue.
   */
  private function missingYamlResponse(): ValidationApiResponseInterface {
    return new ValidationApiResponse(
      valid: FALSE,
      normalized: NULL,
      results: [
        new ValidationIssue(
          key: 'publiccodeYmlVersion',
          description: 'publiccode.yml is missing.',
          line: 0,
          column: 0,
          type: ValidationIssueType::Error,
        ),
      ],
    );
  }

}
