<?php

declare(strict_types=1);

namespace Drupal\Tests\assessments\ExistingSite;

use Drupal\Component\Serialization\Json;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Component\Serialization\Yaml;
use Drupal\Core\Url;
use Drupal\Tests\joinup_test\ExistingSite\JoinupExistingSiteTestBase;
use Drupal\consumers\Entity\Consumer;
use Drupal\joinup_collection\JoinupCollectionHelper;
use GuzzleHttp\RequestOptions;
use Psr\Http\Message\ResponseInterface;

/**
 * Tests assessment report POST resource.
 */
class AssessmentReportResourceTest extends JoinupExistingSiteTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Location playing the role of remote location.
    $dir = 'public://assessment-test';
    \Drupal::service('file_system')->prepareDirectory($dir, FileSystemInterface::CREATE_DIRECTORY);

    $account = $this->createUser();
    $client = Consumer::create([
      'label' => 'Test',
      'owner_id' => $account->id(),
      'user_id' => $account->id(),
      'client_id' => 'client',
      'secret' => 'secret',
      'grant_types' => 'client_credentials',
      'confidential' => TRUE,
      'scopes' => 'assessments',
    ]);
    $client->save();
    $this->markEntityForCleanup($client);

    $this->drupalLogin($account);
  }

  /**
   * Tests validation errors.
   *
   * @param string $scenario
   *   The scenario identifier.
   *
   * @covers \Drupal\joinup_api\OpenApiSchemaValidator::validate
   * @covers \Drupal\assessments\Plugin\rest\resource\AssessmentReportResource
   * @dataProvider providerTestValidation
   */
  public function testValidation(string $scenario): void {
    // Create testing files.
    file_put_contents("public://assessment-test/evil.exe", "qux");
    file_put_contents("public://assessment-test/fat.docx", str_repeat("x", 11000000));
    file_put_contents("public://assessment-test/" . str_repeat('very_', 48) . 'long_name.pdf', 'quxx');

    ['payload' => $payload, 'expect' => $expect] = $this->getTestCase($scenario);
    $response = $this->post($payload);
    $this->assertSame(422, $response->getStatusCode());
    $this->assertSame($expect, Json::decode($response->getBody()->getContents()));
  }

  /**
   * Provides test scenarios for self::testValidation().
   *
   * @return iterable<string, list<string>>
   *   List of scenarios.
   *
   * @see web/modules/custom/assessments/tests/fixture/test_cases.yml
   */
  public static function providerTestValidation(): iterable {
    $scenarios = [
      'Invalid schema',
      'Invalid references',
      'Missing country for a non-EU organisation',
      'Trying to create an EU organisation',
      'UniqueItems violations',
    ];

    foreach ($scenarios as $scenario) {
      yield $scenario => [$scenario];
    }
  }

  /**
   * Tests that empty strings are rejected for mandatory string fields.
   */
  public function testEmptyStringsValidation(): void {
    ['payload' => $payload] = $this->getTestCase('Valid with optional fields omitted');

    // Set all mandatory string fields to empty strings.
    $payload['name'] = '';
    $payload['provider']['name'] = '';
    $payload['binding_requirement'][0]['description'] = '';
    $payload['binding_requirement'][0]['expression'][0]['name'] = '';
    $payload['binding_requirement'][0]['expression'][0]['description'] = '';
    $payload['binding_requirement'][0]['affects'][0]['name'] = '';
    // Add an asset with empty strings to test Asset validation.
    $payload['binding_requirement'][0]['asset'] = [
      [
        'title' => '',
        'description' => '',
        'landing_page' => 'https://example.com',
      ],
    ];

    $response = $this->post($payload);
    $this->assertSame(422, $response->getStatusCode());

    $data = Json::decode($response->getBody()->getContents());
    $this->assertArrayHasKey('errors', $data);
    $errors = $data['errors'];

    // Verify each mandatory field has a minLength error.
    $this->assertContains(
      'Must be at least 1 characters long',
      $errors['/name'] ?? [],
      'Assessment name should reject empty string'
    );
    $this->assertContains(
      'Must be at least 1 characters long',
      $errors['/provider/name'] ?? [],
      'Provider name should reject empty string'
    );
    $this->assertContains(
      'Must be at least 1 characters long',
      $errors['/binding_requirement/0/description'] ?? [],
      'Binding requirement description should reject empty string'
    );
    $this->assertContains(
      'Must be at least 1 characters long',
      $errors['/binding_requirement/0/expression/0/name'] ?? [],
      'Expression name should reject empty string'
    );
    $this->assertContains(
      'Must be at least 1 characters long',
      $errors['/binding_requirement/0/expression/0/description'] ?? [],
      'Expression description should reject empty string'
    );
    $this->assertContains(
      'Must be at least 1 characters long',
      $errors['/binding_requirement/0/affects/0/name'] ?? [],
      'Public service name should reject empty string'
    );
    $this->assertContains(
      'Must be at least 1 characters long',
      $errors['/binding_requirement/0/asset/0/title'] ?? [],
      'Asset title should reject empty string'
    );
    $this->assertContains(
      'Must be at least 1 characters long',
      $errors['/binding_requirement/0/asset/0/description'] ?? [],
      'Asset description should reject empty string'
    );
  }

  /**
   * Tests posting a structured payload returns 201 and echoes the data.
   */
  public function testValidPostDataAndStatus(): void {
    // Create testing files.
    file_put_contents("public://assessment-test/report.pdf", "foo");
    file_put_contents("public://assessment-test/requirement1.pdf", "bar");
    file_put_contents("public://assessment-test/requirement2.pdf", "baz");
    // File for second binding requirement.
    file_put_contents("public://assessment-test/requirement3.pdf", "qux");

    // Create a solution to be referred to in the report.
    $this->createNode([
      'type' => 'solution',
      'title' => 'Used in assessments',
      'description' => ['value' => 'just this', 'format' => 'content_editor'],
      'og_audience' => \Drupal::service('entity.repository')->loadEntityByUuid('node', JoinupCollectionHelper::JOINUP_COLLECTION_UUID),
      'uuid' => 'ad882176-8b1d-4506-9e90-886c5802f1c7',
      'landing_page' => 'https://example.com/the-solution',
      'path' => '/the-solution',
      'field_state' => 'published',
    ]);

    ['payload' => $payload, 'expect' => $expect] = $this->getTestCase('Valid');

    // Add a second binding requirement with its own document to verify
    // documents are attached to the correct binding requirement delta.
    $request = \Drupal::request();
    $baseUrl = $request->getSchemeAndHttpHost();
    $payload['binding_requirement'][] = [
      'expression' => [
        ['name' => 'Second Legal Act', 'description' => 'Another expression'],
      ],
      'description' => 'Second binding requirement',
      'documents' => ["$baseUrl/sites/default/files/assessment-test/requirement3.pdf"],
      'affects' => [
        ['name' => 'Another Service'],
      ],
    ];
    $expect['binding_requirement'][] = [
      'expression' => [
        ['name' => 'Second Legal Act', 'description' => 'Another expression', 'documented_in' => NULL],
      ],
      'description' => 'Second binding requirement',
      'documents' => ["$baseUrl/sites/default/files/assessments/report/requirement3.pdf"],
      'affects' => [
        ['name' => 'Another Service', 'thematic_area' => [], 'country' => []],
      ],
      'has_participation' => [],
      'asset' => [],
      'documented_in' => NULL,
    ];

    $response = $this->post($payload);
    $data = Json::decode($response->getBody()->getContents());

    // Update the expectation with generated UUIDs and URIs.
    $this->assertArrayNotHasKey('errors', $data);
    $uuid = $data['id'];
    $uri = 'http://data.europa.eu/s2k/' . $uuid;
    $expect = ['id' => $uuid, 'uri' => $uri] + $expect;
    $uuid = $data['provider']['id'];
    $providerUri = 'http://data.europa.eu/s2k/' . $uuid;
    $provider = ['id' => $uuid, 'uri' => $providerUri] + $expect['provider'];
    $expect['provider'] = $provider;
    $uuid = $data['binding_requirement'][0]['asset'][0]['id'];
    $assetUri = 'http://data.europa.eu/w21/' . $uuid;
    $asset = ['id' => $uuid, 'uri' => $assetUri] + $expect['binding_requirement'][0]['asset'][0];
    $expect['binding_requirement'][0]['asset'][0] = $asset;
    // Add URI for the referenced existing asset.
    $expect['binding_requirement'][0]['asset'][1]['uri'] = 'http://data.europa.eu/w21/' . $expect['binding_requirement'][0]['asset'][1]['id'];
    // Asset URL is not testable because depends on the environment.
    unset($data['binding_requirement'][0]['asset'][0]['url']);
    unset($data['binding_requirement'][0]['asset'][1]['url']);

    $this->assertEquals($expect, $data);

    // Verify all entities created through the API are published.
    $entityRepository = \Drupal::service('entity.repository');

    // Load the report and verify it's published.
    $report = $entityRepository->loadEntityByUuid('assessment', $data['id']);
    $this->assertTrue($report->isPublished(), 'Assessment report should be published');

    // Verify provider (public organisation) is published.
    $provider = $report->get('provider')->entity;
    $this->assertTrue($provider->isPublished(), 'Provider (public organisation) should be published');

    // Verify documents are attached to the correct binding requirement.
    // First binding requirement should have 2 documents, second should have 1.
    $requirements = $report->get('binding_requirement')->referencedEntities();
    $this->assertCount(2, $requirements[0]->get('requirement_documents'), 'First binding requirement should have 2 documents');
    $this->assertCount(1, $requirements[1]->get('requirement_documents'), 'Second binding requirement should have 1 document');

    // Verify binding requirement paragraph and its referenced entities.
    foreach ($requirements as $requirement) {
      // Verify expression entities are published.
      foreach ($requirement->get('expression')->referencedEntities() as $expression) {
        $this->assertTrue($expression->isPublished(), 'Expression entity should be published');
      }

      // Verify affects (public service) entities are published.
      foreach ($requirement->get('affects')->referencedEntities() as $publicService) {
        $this->assertTrue($publicService->isPublished(), 'Public service (affects) entity should be published');
      }

      // Verify participation entities are published.
      foreach ($requirement->get('has_participation')->referencedEntities() as $participation) {
        $this->assertTrue($participation->isPublished(), 'Participation entity should be published');
      }

      // Verify newly created assets (solutions) are published.
      foreach ($requirement->get('asset')->referencedEntities() as $asset) {
        $this->assertTrue($asset->isPublished(), 'Asset (solution) should be published');
      }
    }

    // Test the GET resource.
    $this->drupalGet('/api/assessments/v1/assessment/' . $data['id']);
    $actual = Json::decode($this->getSession()->getPage()->getContent());
    // Asset URL is not testable because depends on th environment.
    unset($actual['binding_requirement'][0]['asset'][0]['url']);
    unset($actual['binding_requirement'][0]['asset'][1]['url']);

    $assert = $this->assertSession();
    $assert->statusCodeEquals(200);
    // The page is cacheable but this is the first hit.
    $assert->responseHeaderEquals('X-Drupal-Cache', 'MISS');
    $this->assertEquals($expect, $actual);

    // Test that the response is cached.
    $this->drupalGet('/api/assessments/v1/assessment/' . $data['id']);
    $assert->statusCodeEquals(200);
    // The response is retrieved from cache.
    $assert->responseHeaderEquals('X-Drupal-Cache', 'HIT');
    // Asset URL is not testable because depends on the environment.
    unset($actual['binding_requirement'][0]['asset'][0]['url']);
    unset($actual['binding_requirement'][0]['asset'][1]['url']);
    $this->assertEquals($expect, $actual);

    // Cleanup.
    $report = \Drupal::service('entity.repository')->loadEntityByUuid('assessment', $data['id']);
    foreach ($report->get('documents') as $item) {
      $this->markEntityForCleanup($item->entity);
    }
    foreach ($report->get('binding_requirement') as $requirement) {
      foreach ($requirement->entity->get('requirement_documents') as $item) {
        $this->markEntityForCleanup($item->entity);
      }
    }
    $this->markEntityForCleanup($report);
  }

  /**
   * Tests that existing organisations are reused if matched by label.
   *
   * @dataProvider organisationDeduplicationProvider
   */
  public function testOrganisationDeduplication(string $variantName, int $expectedNewOrganisations): void {
    ['payload' => $basePayload] = $this->getTestCase('Valid with optional fields omitted');

    // Submit the first report to create the initial organisation.
    $response = $this->post($basePayload);
    $this->assertSame(201, $response->getStatusCode());
    $data = Json::decode($response->getBody()->getContents());
    $this->assertArrayNotHasKey('errors', $data);
    $firstReportId = $data['id'];
    $firstProviderId = $data['provider']['id'];

    $initialCount = $this->countPublicOrganisations();

    // Submit a second report with the variant name.
    $payload = $basePayload;
    $payload['name'] = "Report for: $variantName";
    $payload['provider']['name'] = $variantName;

    $response = $this->post($payload);
    $this->assertSame(201, $response->getStatusCode(), "Failed for name: '$variantName'");
    $data = Json::decode($response->getBody()->getContents());
    $this->assertArrayNotHasKey('errors', $data);
    $secondReportId = $data['id'];

    // Verify the organisation count matches expectation.
    $this->assertSame(
      $initialCount + $expectedNewOrganisations,
      $this->countPublicOrganisations(),
      "Expected $expectedNewOrganisations new organisation(s) for name: '$variantName'"
    );

    // Verify provider reuse based on expectation.
    if ($expectedNewOrganisations === 0) {
      $this->assertSame(
        $firstProviderId,
        $data['provider']['id'],
        "Should reuse same provider for name: '$variantName'"
      );
    }
    else {
      $this->assertNotSame(
        $firstProviderId,
        $data['provider']['id'],
        "Should create new provider for name: '$variantName'"
      );
    }

    // Cleanup.
    $entityRepository = \Drupal::service('entity.repository');
    foreach ([$firstReportId, $secondReportId] as $reportId) {
      $report = $entityRepository->loadEntityByUuid('assessment', $reportId);
      if ($report) {
        $this->markEntityForCleanup($report);
      }
    }
  }

  /**
   * Data provider for testOrganisationDeduplication.
   *
   * The base provider name in Valid with optional fields omitted is
   * "ACME Corp".
   *
   * @return array
   *   Test cases with variant name and expected new organisation count.
   */
  public static function organisationDeduplicationProvider(): array {
    // Base name from Valid with optional fields omitted test case.
    $baseName = 'ACME Corp';

    return [
      'uppercase' => [mb_strtoupper($baseName), 0],
      'lowercase' => [mb_strtolower($baseName), 0],
      'leading and trailing spaces' => ['  ' . $baseName . '  ', 0],
      'uppercase with spaces' => ['  ' . mb_strtoupper($baseName) . '  ', 0],
      'different name' => ['A Completely Different Organisation', 1],
    ];
  }

  /**
   * Tests posting a payload with optional fields omitted.
   */
  public function testValidPostWithOptionalFieldsOmitted(): void {
    ['payload' => $payload, 'expect' => $expect] = $this->getTestCase('Valid with optional fields omitted');

    $response = $this->post($payload);
    $this->assertSame(201, $response->getStatusCode());
    $data = Json::decode($response->getBody()->getContents());
    $this->assertArrayNotHasKey('errors', $data);

    // Update the expectation with generated UUIDs and URIs.
    $uuid = $data['id'];
    $uri = 'http://data.europa.eu/s2k/' . $uuid;
    $expect = ['id' => $uuid, 'uri' => $uri] + $expect;
    $uuid = $data['provider']['id'];
    $providerUri = 'http://data.europa.eu/s2k/' . $uuid;
    $provider = ['id' => $uuid, 'uri' => $providerUri] + $expect['provider'];
    $expect['provider'] = $provider;

    $this->assertEqualsCanonicalizing($expect, $data);

    // Cleanup.
    $report = \Drupal::service('entity.repository')->loadEntityByUuid('assessment', $data['id']);
    $this->markEntityForCleanup($report);
  }

  /**
   * Tests that 401 errors return JSON response.
   */
  public function testUnauthorizedReturnsJson(): void {
    $url = Url::fromUri('internal:/api/assessments/v1/assessment')
      ->setAbsolute()
      ->toString();

    $response = \Drupal::httpClient()->post($url, [
      RequestOptions::HTTP_ERRORS => FALSE,
      RequestOptions::HEADERS => [
        'Accept' => 'application/json',
        'Content-Type' => 'application/json',
        'Authorization' => 'Bearer invalid_token',
      ],
      RequestOptions::JSON => ['name' => 'Test'],
    ]);

    $this->assertSame(401, $response->getStatusCode());
    $this->assertStringContainsString('application/json', $response->getHeaderLine('Content-Type'));
    $data = Json::decode($response->getBody()->getContents());
    $this->assertIsArray($data);
    $this->assertArrayHasKey('error', $data);
  }

  /**
   * Returns the count of public organisation entities.
   *
   * @return int
   *   The count of public organisations.
   */
  protected function countPublicOrganisations(): int {
    return (int) \Drupal::entityTypeManager()
      ->getStorage('assessment')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'public_organisation')
      ->count()
      ->execute();
  }

  /**
   * Returns a Guzzle response after posting to /api/assessments/v1/assessment.
   *
   * @return \Psr\Http\Message\ResponseInterface
   *   Guzzle response.
   */
  protected function post(array $payload): ResponseInterface {
    $url = Url::fromUri('internal:/api/assessments/v1/assessment')
      ->setAbsolute()
      ->toString();

    return \Drupal::httpClient()->post($url, [
      RequestOptions::HTTP_ERRORS => FALSE,
      RequestOptions::HEADERS => [
        'Authorization' => 'Bearer ' . $this->getAccessToken(),
      ],
      RequestOptions::JSON => $payload,
    ]);
  }

  /**
   * Returns a Guzzle response after getting to /api/assessments/v1/assessment.
   *
   * @return \Psr\Http\Message\ResponseInterface
   *   Guzzle response.
   */
  protected function get(array $payload): ResponseInterface {
    $url = Url::fromUri('internal:/api/assessments/v1/assessment')
      ->setAbsolute()
      ->toString();

    return \Drupal::httpClient()->get($url, [
      RequestOptions::HTTP_ERRORS => FALSE,
      RequestOptions::HEADERS => [
        'Authorization' => 'Bearer ' . $this->getAccessToken(),
      ],
      RequestOptions::JSON => $payload,
    ]);
  }

  /**
   * Provides a test case given its identifier.
   *
   * @param string $id
   *   The test case given its identifier.
   *
   * @return array{payload: array, expect: array}
   *   The test case.
   */
  protected function getTestCase(string $id): array {
    static $data;

    if (!isset($data)) {
      $yaml = file_get_contents(__DIR__ . '/../../fixture/test_cases.yml');
      // Replace environment dependent tokens.
      $yaml = str_replace('__base_url__', $GLOBALS['base_url'], $yaml);
      $data = Yaml::decode($yaml);
    }

    return $data[$id];
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
        'client_id' => 'client',
        'client_secret' => 'secret',
      ],
      RequestOptions::HTTP_ERRORS => FALSE,
    ];

    return Json::decode((string) \Drupal::httpClient()->post($url, $options)->getBody())['access_token'];
  }

  /**
   * Tests that HTTP errors return JSON responses.
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
  }

  /**
   * {@inheritdoc}
   */
  public function tearDown(): void {
    \Drupal::service('file_system')->deleteRecursive('public://assessment-test');
    parent::tearDown();
  }

}
