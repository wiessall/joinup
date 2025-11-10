<?php

declare(strict_types=1);

namespace Drupal\Tests\assessments\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\rest\ResourceResponse;

/**
 * Kernel tests for the Assessment Composer REST resource.
 *
 * These tests directly instantiate the REST resource plugin and invoke
 * its post() method with decoded JSON payloads that mirror the examples in
 * idlocal/test.http, avoiding the need to exercise HTTP routing or OAuth.
 */
class AssessmentComposerResourceTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'serialization',
    'rest',
    'assessments',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  protected function setUp(): void {
    parent::setUp();

    // Install entity schema for assessments module.
    $this->installEntitySchema('assessment');
    $this->installEntitySchema('user');
    $this->installSchema('system', ['sequences']);
    $this->installSchema('user', ['users_data']);
    $this->installConfig(['assessments']);
    $this->installConfig(['system']);
  }

  /**
   * Tests posting a structured payload returns 201 and echoes the data.
   */
  public function testPostStructuredPayload(): void {

    // UI
    $payload = [
      'name' => 'Quarterly review',
      'expression' => [
        [
          'id' => '2',
        ],
        [
          'id' => '3',
        ],
      ],
      'binding_requirement' => [
        [
          'documented_in' => 'https://example.com/law/123',
          'name' => 'Article 5',
        ],
        'binding_requirement' => [
          [
            'documented_in' => 'https://example.com/law/123',
            'name' => 'Article 7','expression' => [
          ],
       ];

    // API
    $payload = [
      'name' => 'Quarterly review',

      'binding_requirement' => [
        [
          'documented_in' => 'https://example.com/law/123',
          'name' => 'Article 5',
          'expression' => [
          [
            'id' => '2',
          ],

        ],
          [
            'documented_in' => 'https://example.com/law/234',
            'name' => 'Article 7','expression' => [
            [
              'id' => '3',
            ],
          ],

          ];

    $payload = [
      'name' => 'Quarterly review',

      'binding_requirement' => [
        [
          'documented_in' => 'https://example.com/law/123',
          'name' => 'Article 5',
          'expression' => [
          [
            'id' => '2',
          ],
          [
            'id' => '3',
          ],
        ],
        ],
        'binding_requirement' => [
        [
          'documented_in' => 'https://example.com/law/123',
          'name' => 'Article 7',
          'expression' => [
          [
            'id' => '2',
          ],
          [
            'id' => '3',
          ],
        ],

        ];











          $payload = [
      'name' => 'Quarterly review',
      'binding_requirement' => [
        [
          'documented_in' => 'https://example.com/law/123',
          'name' => 'Article 5',
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
              'description' => 'Online permit application',
              'spatial' => 'BE',
              'identifier' => 'ps-123',
              'name' => 'Permit Application Service',
              'thematic_area' => 'digital_and_innovation_services',
            ],
          ],
          'has_participation' => [
            [
              'country' => 'BE',
              'participation_type' => 'national_public_sector_body',
            ],
          ],
          'asset' => [
            [
              'name' => 'Portal EU',
              'landing_page' => 'https://portal.example.eu',
            ],
          ],
          'documents' => [
            [
              'file_uri' => 'https://files.example.com/documents/req-1.pdf',
            ],
          ],
        ],
      ],
      'provider' => [
        'name' => 'City of Brussels',
        'description' => 'Local authority',
        'organisation_type' => 'local_public_sector_body',
        'country' => 'BE',
      ],
      'results_in' => [
        [
          'interpretation' => 'Compliant with conditions',
          'judgement' => 'positive',
          'type' => 'final',
          'documents' => [
            [
              'file_uri' => 'https://files.example.com/documents/statement-1.pdf',
            ],
          ],
        ],
      ],
    ];

    /** @var \Drupal\rest\ResourceResponse $response */
    $response = $this->invokeResourcePost($payload);

    $this->assertInstanceOf(ResourceResponse::class, $response);
    $this->assertSame(201, $response->getStatusCode());

    $data = $response->getResponseData();
    $this->assertIsArray($data);

    // Basic shape checks based on AssessmentComposer::post().
    $this->assertSame('accepted', $data['status'] ?? NULL);
    $this->assertArrayHasKey('message', $data);
    $this->assertSame('Quarterly review', $data['received']['name'] ?? NULL);

    // Extracted groups presence.
    $this->assertArrayHasKey('extracted', $data);
    $this->assertSame('Quarterly review', $data['extracted']['assessment']['name'] ?? NULL);
    $this->assertNotEmpty($data['extracted']['binding_requirements'] ?? []);
    $this->assertNotEmpty($data['extracted']['public_services'] ?? []);
    $this->assertNotEmpty($data['extracted']['participations'] ?? []);
    $this->assertNotEmpty($data['extracted']['agents'] ?? []);
    $this->assertNotEmpty($data['extracted']['assets'] ?? []);
    $this->assertNotEmpty($data['extracted']['files'] ?? []);
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
    $invalid = [ [ 'name' => 'Wrong root type' ] ];

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
