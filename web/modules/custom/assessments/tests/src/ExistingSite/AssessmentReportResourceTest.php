<?php

declare(strict_types=1);

namespace Drupal\Tests\assessments\ExistingSite;

use Drupal\Component\Serialization\Json;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Serialization\Yaml;
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
   * @covers \Drupal\joinup_api\OpenApiSchemaValidator::validate
   * @covers \Drupal\assessments\Plugin\rest\resource\AssessmentReportResource
   */
  public function testValidation(): void {
    // Create testing files.
    file_put_contents("public://assessment-test/evil.exe", "qux");
    file_put_contents("public://assessment-test/fat.docx", str_repeat("x", 11000000));
    file_put_contents("public://assessment-test/" . str_repeat('very_', 48) . 'long_name.pdf', 'quxx');

    ['payload' => $payload, 'expect' => $expect] = $this->getTestCase('invalid_schema');
    $response = $this->post($payload);
    $this->assertSame(422, $response->getStatusCode());
    $this->assertSame($expect, Json::decode($response->getBody()->getContents()));

    ['payload' => $payload, 'expect' => $expect] = $this->getTestCase('invalid_references');
    $response = $this->post($payload);
    $this->assertSame(422, $response->getStatusCode());
    $this->assertSame($expect, Json::decode($response->getBody()->getContents()));

    ['payload' => $payload, 'expect' => $expect] = $this->getTestCase('invalid_country_for_eu_organisation');
    $response = $this->post($payload);
    $this->assertSame(422, $response->getStatusCode());
    $this->assertSame($expect, Json::decode($response->getBody()->getContents()));

    ['payload' => $payload, 'expect' => $expect] = $this->getTestCase('invalid_missing_country_for_non_eu_organisation');
    $response = $this->post($payload);
    $this->assertSame(422, $response->getStatusCode());
    $this->assertSame($expect, Json::decode($response->getBody()->getContents()));
  }

  /**
   * Tests posting a structured payload returns 201 and echoes the data.
   */
  public function testValidPostDataAndStatus(): void {
    // Create testing files.
    file_put_contents("public://assessment-test/report.pdf", "foo");
    file_put_contents("public://assessment-test/requirement1.pdf", "bar");
    file_put_contents("public://assessment-test/requirement2.pdf", "baz");

    // Create a solution to be referred in report.
    $this->createNode([
      'type' => 'solution',
      'title' => 'Used in assessments',
      'description' => ['value' => 'just this', 'format' => 'content_editor'],
      'og_audience' => \Drupal::service('entity.repository')->loadEntityByUuid('node', JoinupCollectionHelper::JOINUP_COLLECTION_UUID),
      'uuid' => 'ad882176-8b1d-4506-9e90-886c5802f1c7',
      'landing_page' => 'https://example.com/the-solution',
      'path' => '/the-solution',
    ]);

    ['payload' => $payload, 'expect' => $expect] = $this->getTestCase('valid');

    $response = $this->post($payload);
    $data = Json::decode($response->getBody()->getContents());

    // Update the expectation with generated UUIDs and URIs.
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

    $this->assertEqualsCanonicalizing($expect, $data);

    // Verify all entities created through the API are published.
    $entityRepository = \Drupal::service('entity.repository');

    // Load the report and verify it's published.
    $report = $entityRepository->loadEntityByUuid('assessment', $data['id']);
    $this->assertTrue($report->isPublished(), 'Assessment report should be published');

    // Verify provider (public organisation) is published.
    $provider = $report->get('provider')->entity;
    $this->assertTrue($provider->isPublished(), 'Provider (public organisation) should be published');

    // Verify binding requirement paragraph and its referenced entities.
    foreach ($report->get('binding_requirement')->referencedEntities() as $requirement) {
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

    // Verify statement entities exist. Statement entities don't have
    // isPublished() - they inherit access from the parent report.
    foreach (['legal', 'organisational', 'semantic', 'technical'] as $statementType) {
      if (!$report->get($statementType)->isEmpty()) {
        $statement = $report->get($statementType)->entity;
        $this->assertNotNull($statement, "Statement '$statementType' should exist");
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
    $this->assertEqualsCanonicalizing($expect, $actual);

    // Test that the response is cached.
    $this->drupalGet('/api/assessments/v1/assessment/' . $data['id']);
    $assert->statusCodeEquals(200);
    // The response is retrieved from cache.
    $assert->responseHeaderEquals('X-Drupal-Cache', 'HIT');
    $this->assertEqualsCanonicalizing($expect, $actual);

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
   * Tests posting a payload with optional fields omitted.
   */
  public function testValidPostWithOptionalFieldsOmitted(): void {
    ['payload' => $payload, 'expect' => $expect] = $this->getTestCase('valid_optional_fields_omitted');

    $response = $this->post($payload);
    $this->assertSame(201, $response->getStatusCode());
    $data = Json::decode($response->getBody()->getContents());

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
    $yaml = file_get_contents(__DIR__ . '/../../fixture/test_cases.yml');
    // Replace environment dependent tokens.
    $yaml = str_replace('__base_url__', $GLOBALS['base_url'], $yaml);
    $data = Yaml::decode($yaml);

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
   * {@inheritdoc}
   */
  public function tearDown(): void {
    \Drupal::service('file_system')->deleteRecursive('public://assessment-test');
    parent::tearDown();
  }

}
