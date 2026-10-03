<?php

declare(strict_types=1);

namespace Drupal\Tests\sti_rdf\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\NodeType;
use Drupal\sti_rdf\Service\MappingImporter;
use Drupal\sti_rdf\StiUri;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests bringing rdf_sync mappings from config/sync into active config.
 *
 * The failure this guards is silent and happened on the site: config import
 * was blocked by unrelated differences, the mappings stayed on the previous
 * vocabulary, and a full graph rebuild wrote old class and predicate names
 * under new URIs with nothing reporting it.
 */
#[CoversClass(MappingImporter::class)]
#[Group('sti_rdf')]
final class MappingImporterTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'text',
    'node',
    'serialization',
    'rdf_sync',
    'sti_rdf',
  ];

  private const OLD = [
    'type' => 'https://sti-portal.fao.org/ontology#Innovation',
    'uri_plugin' => 'sti_namespace:source-record',
    'fields' => ['title' => ['value' => ['predicate' => 'http://purl.org/dc/terms/title', 'type' => NULL]]],
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    foreach (['solution', 'collection', 'page'] as $type) {
      NodeType::create(['type' => $type, 'name' => $type])->save();
    }
    // Active: solution and collection carry the previous mapping, written
    // raw so that rdf_sync never reacts to it.
    $this->setActive('node.type.solution', self::OLD);
    $this->setActive('node.type.collection', [
      'type' => 'http://www.w3.org/ns/dcat#Catalog',
      'uri_plugin' => 'sti_namespace:collection',
      'fields' => [
        'solutions' => ['target_id' => ['predicate' => 'http://www.w3.org/ns/dcat#dataset', 'type' => 'resource']],
      ],
    ]);
    $this->setActive('node.type.page', self::OLD);

    // config/sync: solution moves to the new class, collection loses its
    // dataset list, page loses its mapping altogether, and a bundle that
    // does not exist on the site has one.
    $sync = $this->container->get('config.storage.sync');
    $new = ['type' => StiUri::STI . 'SourceRecord'] + self::OLD;
    $sync->write('node.type.solution', ['type' => 'solution', 'third_party_settings' => ['rdf_sync' => $new]]);
    $sync->write('node.type.collection', [
      'type' => 'collection',
      'third_party_settings' => [
        'rdf_sync' => [
          'uri_plugin' => 'sti_namespace:collection',
          'type' => 'http://www.w3.org/ns/dcat#Catalog',
          'fields' => [],
        ],
      ],
    ]);
    $sync->write('node.type.page', ['type' => 'page']);
    $sync->write('node.type.absent', ['type' => 'absent', 'third_party_settings' => ['rdf_sync' => $new]]);
  }

  /**
   * The diff lists what differs and what cannot be imported; nothing more.
   */
  public function testDiffReportsWithoutWriting(): void {
    $diff = $this->importer()->diff();

    $this->assertSame(['node.type.collection', 'node.type.page', 'node.type.solution'], array_keys($diff['changed']));
    $this->assertSame(['node.type.absent'], $diff['missing']);
    $this->assertNull($diff['changed']['node.type.page']['to']);
    $this->assertSame(self::OLD['type'], $this->config('node.type.solution')->get('third_party_settings.rdf_sync.type'), 'A diff writes nothing.');
  }

  /**
   * Apply writes the mapping only, and a second run finds nothing to do.
   */
  public function testApplyWritesMappingsOnly(): void {
    $names = $this->importer()->apply();

    $this->assertSame(['node.type.collection', 'node.type.page', 'node.type.solution'], $names);
    $solution = $this->config('node.type.solution');
    $this->assertSame(StiUri::STI . 'SourceRecord', $solution->get('third_party_settings.rdf_sync.type'));
    $this->assertSame('solution', $solution->get('name'), 'Nothing outside the mapping is replaced by config/sync.');
    $this->assertSame([], $this->config('node.type.collection')->get('third_party_settings.rdf_sync.fields'), 'The dataset list is gone.');
    $this->assertNull($this->config('node.type.page')->get('third_party_settings.rdf_sync'), 'A mapping removed from config/sync is removed.');
    $this->assertTrue($this->config('node.type.absent')->isNew(), 'Config missing on the site is not created.');

    $this->assertSame([], $this->importer()->diff()['changed']);
    $this->assertSame([], $this->importer()->apply());
  }

  /**
   * Key order alone is not a difference.
   */
  public function testKeyOrderIsIgnored(): void {
    $this->importer()->apply();
    $mapping = $this->config('node.type.solution')->get('third_party_settings.rdf_sync');
    $this->setActive('node.type.solution', array_reverse($mapping, TRUE));

    $this->assertArrayNotHasKey('node.type.solution', $this->importer()->diff()['changed']);
  }

  /**
   * Writes an rdf_sync mapping into active config, bypassing entity hooks.
   */
  private function setActive(string $name, array $mapping): void {
    $this->config($name)->set('third_party_settings.rdf_sync', $mapping)->save();
  }

  /**
   * Returns the service under test.
   */
  private function importer(): MappingImporter {
    return $this->container->get('sti_rdf.mapping_importer');
  }

}
