<?php

declare(strict_types=1);

namespace Drupal\Tests\assessments\Kernel;

use Drupal\Component\Serialization\Json;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Serialization\Yaml;
use Drupal\Tests\joinup_test\ExistingSite\JoinupExistingSiteTestBase;
use Drupal\joinup_collection\JoinupCollectionHelper;
use Drupal\rest\ResourceResponse;

/**
 * Kernel tests for the Assessment Composer REST resource.
 *
 * These tests directly instantiate the REST resource plugin and invoke
 * its post() method with decoded JSON payloads that mirror the examples in
 * idlocal/test.http, avoiding the need to exercise HTTP routing or OAuth.
 */
class AssessmentComposerResourceTest extends JoinupExistingSiteTestBase {

  /**
   * Location playing the role of remote location.
   */
  protected string $testingFileDir = DRUPAL_ROOT . '/sites/default/files/assessment-test';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    \Drupal::service('file_system')->prepareDirectory($this->testingFileDir, FileSystemInterface::CREATE_DIRECTORY);
  }

  /**
   * Tests validation errors.
   *
   * @covers \Drupal\joinup_api\OpenApiSchemaValidator::validate
   * @covers \Drupal\assessments\Plugin\rest\resource\AssessmentReportResource
   */
  public function testValidation(): void {
    // Create testing files.
    file_put_contents("$this->testingFileDir/evil.exe", "qux");
    file_put_contents("$this->testingFileDir/fat.docx", str_repeat("X",  11000000));
    file_put_contents("$this->testingFileDir/" . str_repeat('very_', 48) . 'long_name.pdf', 'quxx');

    ['payload' => $payload, 'expect' => $expect] = $this->getTestCase('invalid_schema');
    $response = $this->invokeResourcePost($payload);
    $data = $response->getResponseData();
    $this->assertSame(422, $response->getStatusCode());
    $this->assertSame($expect, $data);

    ['payload' => $payload, 'expect' => $expect] = $this->getTestCase('invalid_references');
    $response = $this->invokeResourcePost($payload);
    $data = $response->getResponseData();
    $this->assertSame(422, $response->getStatusCode());
    $this->assertSame($expect, $data);
  }

  /**
   * Tests posting a structured payload returns 201 and echoes the data.
   */
  public function testValidPost(): void {
    // Create testing files.
    file_put_contents("$this->testingFileDir/report.pdf", "foo");
    file_put_contents("$this->testingFileDir/requirement1.pdf", "bar");
    file_put_contents("$this->testingFileDir/requirement2.pdf", "baz");

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

    $response = $this->invokeResourcePost($payload);
    $data = $response->getResponseData();

    // Update the expectation with generated UUIDs.
    $uuid = $data['id'];
    $expect = ['id' => $uuid] + $expect;
    $uuid = $data['provider']['id'];
    $provider = ['id' => $uuid] + $expect['provider'];
    $expect['provider'] = $provider;
    $uuid = $data['binding_requirement'][0]['asset'][0]['id'];
    $asset = ['id' => $uuid] + $expect['binding_requirement'][0]['asset'][0];
    $expect['binding_requirement'][0]['asset'][0] = $asset;
    // Asset URL is not testable because depends on th environment.
    unset($data['binding_requirement'][0]['asset'][0]['url']);
    unset($data['binding_requirement'][0]['asset'][1]['url']);

    $this->assertEqualsCanonicalizing($expect, $data);

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
   * Helper to instantiate the REST resource and call post().
   */
  protected function invokeResourcePost(array $payload): ResourceResponse {
    /** @var \Drupal\rest\Plugin\Type\ResourcePluginManager $manager */
    $manager = \Drupal::service('plugin.manager.rest');

    // Create the plugin instance as the container-aware plugin via manager.
    $plugin = $manager->createInstance('assessment_report', []);

    // Call the resource's post() method directly with the decoded body.
    // @phpstan-ignore-next-line Method is defined on the plugin class.
    $response = $plugin->post($payload);
    $this->assertInstanceOf(ResourceResponse::class, $response);

    return $response;
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
   * {@inheritdoc}
   */
  public function tearDown(): void {
    \Drupal::service('file_system')->deleteRecursive($this->testingFileDir);
    parent::tearDown();
  }

}
