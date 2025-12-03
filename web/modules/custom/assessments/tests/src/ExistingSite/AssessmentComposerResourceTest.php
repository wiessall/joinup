<?php

declare(strict_types=1);

namespace Drupal\Tests\assessments\Kernel;

use Drupal\Tests\joinup_test\ExistingSite\JoinupExistingSiteTestBase;
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
   * Tests posting a structured payload returns 201 and echoes the data.
   */
  public function testPostStructuredPayload(): void {
    $payload = [
      'name' => 'Quarterly review',
      'binding_requirement' => [
        [
          'documented_in' => 'https://example.com/law/123',
          'description' => 'This is some description.',
          'expression' => [
            [
              'name' => 'Legal Act 2024',
              'description' => 'Reference to an EU legal expression',
              'documented_in' => 'https://example.com/eli/act',
              'expression_type' => 'http://data.europa.eu/eli/ontology#LegalExpression',
            ],
          ],
          'affects' => [
            [
              'spatial' => 'BE',
              'identifier' => 'ps-123',
              'name' => 'Permit Application Service',
              'thematic_area' => 'core_governance_and_administration',
            ],
          ],
          'has_participation' => [
            [
              // Required by participation mapping.
              'name' => 'BE participant',
              'country' => 'BE',
              'type' => 'local_public_sector_body',
            ],
          ],
          'asset' => [
            [
              'name' => 'Portal EU',
              'landing_page' => 'https://portal.example.eu',
            ],
          ],
          // Optional: 'documents' => [ ... ],.
        ],
      ],
      // Parser expects all reference properties as arrays, even when single.
      'provider' => [
        [
          'name' => 'City of Brussels',
          'type' => 'local_public_sector_body',
          'country' => 'BE',
        ],
      ],
      'results_in' => [
        [
          'interpretation' => 'Compliant with conditions',
          'judgement' => 'positive',
          'type' => 'legal',
        ],
      ],
    ];

    /** @var \Drupal\rest\ResourceResponse $response */
    $response = $this->invokeResourcePost($payload);

    $this->assertInstanceOf(ResourceResponse::class, $response);
    $this->assertSame(201, $response->getStatusCode());

    $data = $response->getResponseData();
    $this->assertIsArray($data);
  }

  /**
   * Tests minimal valid payload.
   */
  public function testPostMinimalPayload(): void {
    $payload = [
      'name' => 'Test Assessment',
      // Optional fields omitted.
    ];

    $response = $this->invokeResourcePost($payload);
    $this->assertSame(201, $response->getStatusCode());

    $data = $response->getResponseData();
    $this->assertSame('Test Assessment', $data['extracted']['assessment']['name'] ?? NULL);
    // Optional extracted groups default to empty arrays when not supplied.
    $this->assertIsArray($data['extracted']['binding_requirements'] ?? []);
    $this->assertIsArray($data['extracted']['statements'] ?? []);
  }

  /**
   * Tests invalid payload (array of objects instead of a single object).
   */
  public function testPostInvalidPayload(): void {
    $invalid = [['name' => 'Wrong root type']];

    $response = $this->invokeResourcePost($invalid);
    $this->assertSame(400, $response->getStatusCode());

    $data = $response->getResponseData();
    $this->assertSame('Payload must be a single JSON object.', $data['error'] ?? NULL);
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

}
