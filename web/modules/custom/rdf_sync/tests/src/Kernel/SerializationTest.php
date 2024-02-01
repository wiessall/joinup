<?php

declare(strict_types = 1);

namespace Drupal\Tests\rdf_sync\Kernel;

use Drupal\Core\Serialization\Yaml;
use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\field\Traits\EntityReferenceFieldCreationTrait;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\node\Entity\Node;
use Drupal\rdf_sync_test\Category;

/**
 * Tests RDF serialization.
 *
 * @group rdf_sync
 */
class SerializationTest extends KernelTestBase {

  use EntityReferenceFieldCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'field',
    'filter',
    'node',
    'rdf_sync',
    'rdf_sync_test',
    'serialization',
    'system',
    'taxonomy',
    'text',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('taxonomy_term');
    $this->installEntitySchema('node');
    $this->installEntitySchema('user');
    $this->installConfig(['rdf_sync_test']);

    // Add a body field.
    FieldStorageConfig::create([
      'entity_type' => 'node',
      'type' => 'text_with_summary',
      'field_name' => 'body',
      'cardinality' => 1,
    ])->save();
    FieldConfig::create([
      'entity_type' => 'node',
      'bundle' => 'page',
      'field_name' => 'body',
      'label' => $this->randomString(),
    ])
      // Using the computed 'processed' column instead of 'value' to get the
      // text format applied.
      ->setThirdPartySetting('rdf_sync', 'processed', [
        'predicate' => 'http://example.com/page/body',
        'type' => NULL,
      ])->save();

    $this->createEntityReferenceField('node', 'page', 'category', $this->randomString(), 'taxonomy_term', 'default', ['target_bundles' => ['category']]);
    FieldConfig::loadByName('node', 'page', 'category')
      ->setThirdPartySetting('rdf_sync', 'target_id', [
        'predicate' => 'http://example.com/page/category',
        'type' => 'resource',
      ])->save();

    // Start on clean.
    $this->container->get('entity_field.manager')->clearCachedFieldDefinitions();
    $this->container->get('entity_type.bundle.info')->clearCachedBundles();
  }

  /**
   * @covers \Drupal\rdf_sync\Normalizer\RdfSyncNormalizer
   * @covers \Drupal\rdf_sync\Encoder\RdfSyncEncoder
   *
   * @dataProvider providerTestSerialization
   */
  public function testSerialization(string $format, array $expectedSerialization): void {
    $category = Category::create([
      'vid' => 'category',
      'name' => 'Series',
      'description' => [
        'value' => '<b>7</b> seasons',
        'format' => 'html',
      ],
      'uri' => 'http://example.com/category/id/1',
    ]);
    $page = Node::create([
      'type' => 'page',
      'title' => 'Mad Men',
      'uri' => 'http://example.com/page/id/1',
      'body' => [
        'value' => '<strong>Strong</strong> is stripped while <em>Emphasis</em> is kept',
        'format' => 'html',
      ],
      'category' => $category,
    ]);

    $actualTermSerialization = trim($this->container->get('serializer')->serialize($category, $format));
    $this->assertSame($expectedSerialization['taxonomy_term'], $actualTermSerialization);

    $actualNodeSerialization = trim($this->container->get('serializer')->serialize($page, $format));
    $this->assertSame($expectedSerialization['node'], $actualNodeSerialization);
  }

  /**
   * Provides test cases for self::testSerialization())
   *
   * @return array
   *   A list of testing cases.
   *
   * @see self::testSerialization()
   */
  public function providerTestSerialization(): array {
    $cases = Yaml::decode(file_get_contents(__DIR__ . '/../../fixtures/serialization.yml'));
    return array_map(function (string $format) use ($cases): array {
      return [$format, $cases[$format]];
    }, array_keys($cases));
  }

}
