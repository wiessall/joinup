<?php

declare(strict_types=1);

namespace Drupal\Tests\assessments\Kernel;

use Drupal\assessments\AssessmentInterface;
use Drupal\collection\Entity\Collection;
use Drupal\joinup_solution\Entity\Solution;
use Drupal\og\OgGroupAudienceHelperInterface;
use Drupal\Tests\joinup_test\ExistingSite\JoinupExistingSiteTestBase;
use Drupal\assessments\Entity\Assessment\Asset;
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
    $payload = $this->baseValidPayload();

    $response = $this->invokeResourcePost($payload);
    $data = $response->getResponseData();
    // The IDs have been returned along with the original array. The URIs also
    // validate creation of their respective entities as they are created on
    // save.
    $this->assertArrayHasKey('id', $data, 'Assessment ID is present in the response.');
    $this->assertNotFalse(filter_var($data['id'], FILTER_VALIDATE_URL));
    $this->assertArrayHasKey('id', $data['binding_requirement'][0], 'Binding requirement ID is present in the response.');
    $this->assertNotFalse(filter_var($data['binding_requirement'][0]['id'], FILTER_VALIDATE_URL));
    $this->assertArrayHasKey('id', $data['provider'][0], 'Provider ID is present in the response.');
    $this->assertNotFalse(filter_var($data['provider'][0]['id'], FILTER_VALIDATE_URL));
    $this->assertArrayHasKey('id', $data['results_in'][0], 'Results in ID is present in the response.');
    $this->assertNotFalse(filter_var($data['results_in'][0]['id'], FILTER_VALIDATE_URL));
    $this->assertArrayHasKey('id', $data['binding_requirement'][0]['affects'][0], 'Affected service ID is present in the response.');
    $this->assertNotFalse(filter_var($data['binding_requirement'][0]['affects'][0]['id'], FILTER_VALIDATE_URL));
    $this->assertArrayHasKey('id', $data['binding_requirement'][0]['expression'][0], 'Expression ID is present in the response.');
    $this->assertNotFalse(filter_var($data['binding_requirement'][0]['expression'][0]['id'], FILTER_VALIDATE_URL));
    $this->assertArrayHasKey('id', $data['binding_requirement'][0]['has_participation'][0], 'Participation ID is present in the response.');
    $this->assertNotFalse(filter_var($data['binding_requirement'][0]['has_participation'][0]['id'], FILTER_VALIDATE_URL));
    $this->assertSame(201, $response->getStatusCode());
  }

  /**
   * Tests that errors are returned for unknown properties.
   */
  public function testUnexpectedPropertiesReturnErrors(): void {
    $payload = $this->baseValidPayload();
    // Add unexpected root property.
    $payload['foo'] = 'bar';
    // Add unexpected nested property under affects.
    $payload['binding_requirement'][0]['affects'][0]['unexpected'] = TRUE;

    $response = $this->invokeResourcePost($payload);
    $this->assertSame(422, $response->getStatusCode());
    $data = $response->getResponseData();
    $this->assertIsArray($data['errors'] ?? NULL);
    $messages = implode("\n", $data['errors']);
    $this->assertStringContainsString('assessment: Unexpected property: foo', $messages);
    $this->assertStringContainsString('assessment > binding_requirement > affects: Unexpected property: unexpected', $messages);
  }

  /**
   * Tests that errors are returned for missing required properties.
   */
  public function testMissingRequiredPropertiesReturnErrors(): void {
    $payload = $this->baseValidPayload();
    // Remove required properties, both text and reference.
    unset($payload['name']);
    unset($payload['binding_requirement']);

    $response = $this->invokeResourcePost($payload);
    $this->assertSame(422, $response->getStatusCode());
    $data = $response->getResponseData();
    $messages = implode("\n", $data['errors'] ?? []);
    $this->assertStringContainsString('assessment: Missing required property: name', $messages);
    $this->assertStringContainsString('assessment: Missing required property: binding_requirement', $messages);
  }

  /**
   * Invalid values are passed as some of the referenced types (invalid ids).
   */
  public function testInvalidReferenceIdsReturnErrors(): void {
    $payload = $this->baseValidPayload();
    // Provide non-existent ids for `provider` and `affects` references.
    $payload['provider'] = [['id' => 99999999]];
    $payload['binding_requirement'][0]['affects'] = [['id' => 88888888]];

    $response = $this->invokeResourcePost($payload);
    $this->assertSame(422, $response->getStatusCode());
    $data = $response->getResponseData();
    $messages = implode("\n", $data['errors'] ?? []);
    // Class short names appear in the message per
    // ResourceParser::getClassShortName().
    $this->assertStringContainsString('assessment > provider: Provided id 99999999 does not reference a valid entity', $messages);
    $this->assertStringContainsString('assessment > binding_requirement > affects: Provided id 88888888 does not reference a valid entity', $messages);
  }

  /**
   * Tests that an asset can be reused.
   */
  public function testAssetCanBeReused(): void {
    // Create an asset entity that we will reference twice in the payload.
    $existingAssetUri = $this->createAsset('Reusable portal', 'https://reusable.example.eu');
    $beforeCount = $this->countAssessmentBundle('asset');

    $payload = $this->baseValidPayload();
    // Replace assets with two references to the same existing id.
    $payload['binding_requirement'][0]['asset'] = [
      ['id' => $existingAssetUri],
      ['id' => $existingAssetUri],
    ];

    $response = $this->invokeResourcePost($payload);
    $this->assertSame(201, $response->getStatusCode(), 'Existing asset ids should be accepted without creating new assets.');

    $afterCount = $this->countAssessmentBundle('asset');
    $this->assertSame($beforeCount, $afterCount, 'No new asset entities should be created.');

    $data = $response->getResponseData();
    $assets = $data['binding_requirement'][0]['asset'] ?? [];
    $this->assertCount(2, $assets);
    $this->assertSame($existingAssetUri, $assets[0]['id'] ?? NULL);
    $this->assertSame($existingAssetUri, $assets[1]['id'] ?? NULL);
  }

  /**
   * Build a base valid payload for reuse across tests.
   */
  protected function baseValidPayload(): array {
    return [
      'name' => 'Quarterly review',
      'binding_requirement' => [
        [
          'documented_in' => 'https://example.com/law/123',
          'description' => 'This is some description.',
          'affects' => [
            [
              'identifier' => 'ps-123',
              'name' => 'Permit Application Service',
              'thematic_area' => 'core_governance_and_administration',
            ],
          ],
          'expression' => [
            [
              'name' => 'Legal Act 2024',
              'description' => 'Reference to an EU legal expression',
              'documented_in' => 'https://example.com/eli/act',
              'expression_type' => 'http://data.europa.eu/eli/ontology#LegalExpression',
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
//          'asset' => [
//            [
//              'name' => 'Portal EU',
//              'landing_page' => 'https://portal.example.eu',
//            ],
//          ],
        ],
      ],
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
  }

  /**
   * Count assessment entities for a specific bundle.
   */
  protected function countAssessmentBundle(string $bundle): int {
    $query = \Drupal::entityTypeManager()->getStorage('assessment')->getQuery();
    $query->condition('type', $bundle);
    $query->accessCheck(FALSE);
    return (int) $query->count()->execute();
  }

  /**
   * Create an Asset entity and return its numeric id.
   */
  protected function createAsset(string $name, string $landingPage): string {
    $group = \Drupal::service('entity.repository')->loadEntityByUuid('node', AssessmentInterface::SOLUTION_COLLECTOR);
    $asset = Solution::create([
      'type' => 'asset',
      'title' => $name,
      'landing_page' => $landingPage,
      OgGroupAudienceHelperInterface::DEFAULT_FIELD => [
        ['target_id' => $group->id()],
      ],
      'field_state' => 'published',
    ]);
    $asset->save();
    return $asset->getUri();
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
