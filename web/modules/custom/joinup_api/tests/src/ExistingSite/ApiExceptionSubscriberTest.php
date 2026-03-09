<?php

declare(strict_types=1);

namespace Drupal\Tests\joinup_api\ExistingSite;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Url;
use Drupal\Tests\joinup_test\ExistingSite\JoinupExistingSiteTestBase;
use Drupal\consumers\Entity\Consumer;
use Drupal\joinup_api\EventSubscriber\ApiExceptionSubscriber;
use GuzzleHttp\RequestOptions;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Http\Message\ResponseInterface;

/**
 * Tests that HTTP errors on API paths return JSON responses.
 */
#[CoversClass(ApiExceptionSubscriber::class)]
class ApiExceptionSubscriberTest extends JoinupExistingSiteTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $account = $this->createUser();
    $client = Consumer::create([
      'label' => 'Joinup API Test',
      'owner_id' => $account->id(),
      'user_id' => $account->id(),
      'client_id' => 'joinup_api_test_client',
      'secret' => 'joinup_api_test_secret',
      'grant_types' => 'client_credentials',
      'confidential' => TRUE,
      'scopes' => 'assessments',
    ]);
    $client->save();
    $this->markEntityForCleanup($client);
  }

  /**
   * Tests that HTTP errors return JSON responses.
   *
   * Covers the main path through handleException(): path under /api/ with
   * Accept: application/json and an HttpExceptionInterface throwable.
   */
  public function testHttpErrorsReturnJson(): void {
    $httpClient = \Drupal::httpClient();
    $baseUrl = Url::fromUri('internal:/api/assessments/v1/assessment')
      ->setAbsolute()
      ->toString();

    // Test 401 Unauthorized (invalid token).
    $response = $httpClient->post($baseUrl, [
      RequestOptions::HTTP_ERRORS => FALSE,
      RequestOptions::HEADERS => [
        'Accept' => 'application/json',
        'Content-Type' => 'application/json',
        'Authorization' => 'Bearer invalid_token',
      ],
      RequestOptions::JSON => ['name' => 'Test'],
    ]);
    $this->assertJsonErrorResponse($response, 401);

    // Test 403 Forbidden (non-existent assessment).
    $response = $httpClient->get($baseUrl . '/non-existent-uuid', [
      RequestOptions::HTTP_ERRORS => FALSE,
      RequestOptions::HEADERS => [
        'Accept' => 'application/json',
        'Authorization' => 'Bearer ' . $this->getAccessToken(),
      ],
    ]);
    $this->assertJsonErrorResponse($response, 403);

    // Test 405 Method Not Allowed (PATCH not supported).
    $response = $httpClient->patch($baseUrl, [
      RequestOptions::HTTP_ERRORS => FALSE,
      RequestOptions::HEADERS => [
        'Accept' => 'application/json',
        'Content-Type' => 'application/json',
        'Authorization' => 'Bearer ' . $this->getAccessToken(),
      ],
      RequestOptions::JSON => ['name' => 'Test'],
    ]);
    $this->assertJsonErrorResponse($response, 405);
  }

  /**
   * Tests that a 5xx error on an API path returns a JSON response.
   *
   * Covers the on5xx() handler. Maintenance mode causes Drupal's
   * MaintenanceModeSubscriber to throw a ServiceUnavailableHttpException (503)
   * during kernel.request, which is then caught and converted to JSON by the
   * ApiExceptionSubscriber.
   */
  public function testApiPath5xxErrorReturnsJson(): void {
    \Drupal::state()->set('system.maintenance_mode', 1);

    try {
      $url = Url::fromUri('internal:/api/assessments/v1/assessment')
        ->setAbsolute()
        ->toString();

      $response = \Drupal::httpClient()->get($url, [
        RequestOptions::HTTP_ERRORS => FALSE,
        RequestOptions::HEADERS => [
          'Accept' => 'application/json',
        ],
      ]);

      $this->assertJsonErrorResponse($response, 503);
    }
    finally {
      \Drupal::state()->set('system.maintenance_mode', 0);
    }
  }

  /**
   * Asserts that an HTTP response is a JSON error with the expected status.
   *
   * @param \Psr\Http\Message\ResponseInterface $response
   *   The HTTP response.
   * @param int $expectedStatus
   *   The expected HTTP status code.
   */
  private function assertJsonErrorResponse(ResponseInterface $response, int $expectedStatus): void {
    $this->assertSame($expectedStatus, $response->getStatusCode());
    $this->assertStringContainsString('application/json', $response->getHeaderLine('Content-Type'));
    $data = Json::decode($response->getBody()->getContents());
    $this->assertIsArray($data);
    $this->assertArrayHasKey('error', $data);
    $this->assertIsString($data['error']);
    $this->assertNotEmpty($data['error']);
  }

  /**
   * Obtains an access token.
   *
   * @return string
   *   The OAuth2 access token.
   */
  protected function getAccessToken(): string {
    $url = Url::fromRoute('oauth2_token.token')->setAbsolute()->toString();

    $options = [
      RequestOptions::HEADERS => [
        'Content-Type' => 'application/x-www-form-urlencoded',
        'Accept' => 'application/json',
      ],
      RequestOptions::FORM_PARAMS => [
        'grant_type' => 'client_credentials',
        'client_id' => 'joinup_api_test_client',
        'client_secret' => 'joinup_api_test_secret',
      ],
      RequestOptions::HTTP_ERRORS => FALSE,
    ];

    return Json::decode((string) \Drupal::httpClient()->post($url, $options)->getBody())['access_token'];
  }

}
