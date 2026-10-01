<?php

declare(strict_types=1);

namespace Drupal\Tests\sti_rdf\Kernel;

use Drupal\Core\Database\Connection;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;
use Drupal\sti_rdf\Service\UriReminter;
use Drupal\sti_rdf\StiUri;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\taxonomy\TermInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the P0 move to https://sti-portal.fao.org/id/{type}/{uuid}.
 *
 * Every failure here is silent: a row that is skipped keeps its old URI and
 * the site carries on. The invariants asserted are the ones a deploy relies
 * on: legacy URIs are gone, external URIs are untouched, unmapped bundles are
 * untouched (including Joinup's own bundles on w21), a second run changes
 * nothing, and an unknown bundle on the bare sti_namespace plugin stops the
 * run instead of being guessed.
 *
 * Entities are created before their bundles are mapped, and the mapping is
 * written to raw config, so that rdf_sync never attempts a triplestore write.
 */
#[CoversClass(UriReminter::class)]
#[Group('sti_rdf')]
final class UriReminterTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'field',
    'text',
    'filter',
    'node',
    'taxonomy',
    'serialization',
    'rdf_sync',
    'sti_rdf',
  ];

  /**
   * A source record on the bare legacy plugin.
   */
  private NodeInterface $solution;

  /**
   * A collection in Joinup's w21 namespace.
   */
  private NodeInterface $collection;

  /**
   * A node of an unmapped bundle, which must stay untouched.
   */
  private NodeInterface $page;

  /**
   * A term whose stored term_uri was minted under the legacy prefix.
   */
  private TermInterface $mintedTerm;

  /**
   * A term carrying an external (AGROVOC) URI.
   */
  private TermInterface $externalTerm;

  /**
   * A claim property term from a vocabulary with no URI plugin yet.
   */
  private TermInterface $claimProperty;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('taxonomy_term');
    $this->installSchema('rdf_sync', ['rdf_sync_uri']);

    foreach (['solution', 'collection', 'page'] as $type) {
      NodeType::create(['type' => $type, 'name' => $type])->save();
    }
    foreach (['topics', 'claim_properties'] as $vid) {
      Vocabulary::create(['vid' => $vid, 'name' => $vid])->save();
    }
    FieldStorageConfig::create([
      'field_name' => 'term_uri',
      'entity_type' => 'taxonomy_term',
      'type' => 'string',
    ])->save();
    FieldConfig::create([
      'field_name' => 'term_uri',
      'entity_type' => 'taxonomy_term',
      'bundle' => 'topics',
    ])->save();

    $this->solution = Node::create(['type' => 'solution', 'title' => 'S']);
    $this->solution->save();
    $this->collection = Node::create(['type' => 'collection', 'title' => 'C']);
    $this->collection->save();
    $this->page = Node::create(['type' => 'page', 'title' => 'P']);
    $this->page->save();

    $this->mintedTerm = Term::create(['vid' => 'topics', 'name' => 'Minted']);
    $this->mintedTerm->set('term_uri', 'http://sti-portal.fao.org/topics/' . $this->uuid());
    $this->mintedTerm->save();
    $this->externalTerm = Term::create(['vid' => 'topics', 'name' => 'AGROVOC']);
    $this->externalTerm->set('term_uri', 'http://aims.fao.org/aos/agrovoc/c_8113');
    $this->externalTerm->save();
    $this->claimProperty = Term::create(['vid' => 'claim_properties', 'name' => 'readiness']);
    $this->claimProperty->save();

    $this->map('node_type', 'solution', 'https://sti-portal.fao.org/sti#SourceRecord', 'sti_namespace');
    $this->map('node_type', 'collection', 'http://www.w3.org/ns/dcat#Catalog', 'joinup_po_namespace:w21');
    $this->map('taxonomy_vocabulary', 'topics', 'http://www.w3.org/2004/02/skos/core#Concept', 'sti_namespace', 'term_uri');
    $this->map('taxonomy_vocabulary', 'claim_properties', 'http://www.w3.org/1999/02/22-rdf-syntax-ns#Property', NULL);

    $this->row('node', $this->solution, 'http://sti-portal.fao.org/solution/' . $this->solution->uuid());
    $this->row('node', $this->collection, 'http://data.europa.eu/w21/' . $this->collection->uuid());
    // A legacy-looking URI on an unmapped bundle must survive.
    $this->row('node', $this->page, 'http://sti-portal.fao.org/page/' . $this->page->uuid());
    $this->row('taxonomy_term', $this->mintedTerm, $this->mintedTerm->get('term_uri')->value);
    $this->row('taxonomy_term', $this->externalTerm, 'http://aims.fao.org/aos/agrovoc/c_8113');
    $this->row('taxonomy_term', $this->claimProperty, 'http://data.fao.org/ird/wocat#readiness');
  }

  /**
   * Config moves first, then every legacy URI is rewritten, then nothing.
   */
  public function testRemintRewritesLegacyAndOnlyLegacy(): void {
    $reminter = $this->reminter();

    $this->assertSame(4, $reminter->applyConfigChanges());
    $this->assertSame('sti_namespace:source-record', $this->plugin('node.type.solution'));
    $this->assertSame('sti_namespace:collection', $this->plugin('node.type.collection'));
    $this->assertSame('sti_namespace:concept', $this->plugin('taxonomy.vocabulary.topics'));
    $this->assertSame('sti_namespace:property', $this->plugin('taxonomy.vocabulary.claim_properties'));
    $this->assertNull($this->plugin('node.type.page'));

    $this->assertNotEmpty($reminter->verify(), 'Legacy URIs are reported before the rewrite.');
    // Four rdf_sync_uri rows plus the one stored term_uri value. Batch size 1
    // exercises the cursor across every scope.
    $this->assertSame(5, $this->runRemint($reminter, 1));

    $this->assertSame(StiUri::forEntity('source-record', $this->solution->uuid()), $this->storedUri('node', $this->solution));
    $this->assertSame(StiUri::forEntity('collection', $this->collection->uuid()), $this->storedUri('node', $this->collection));
    $this->assertSame(StiUri::forEntity('property', $this->claimProperty->uuid()), $this->storedUri('taxonomy_term', $this->claimProperty));
    $this->assertSame(StiUri::forEntity('concept', $this->mintedTerm->uuid()), $this->storedUri('taxonomy_term', $this->mintedTerm));

    // The stored uri_field_name value moves with the rdf_sync_uri row.
    $this->assertSame(StiUri::forEntity('concept', $this->mintedTerm->uuid()), $this->termUriFieldValue($this->mintedTerm));

    // External URIs and unmapped bundles are untouched.
    $this->assertSame('http://aims.fao.org/aos/agrovoc/c_8113', $this->storedUri('taxonomy_term', $this->externalTerm));
    $this->assertSame('http://aims.fao.org/aos/agrovoc/c_8113', $this->termUriFieldValue($this->externalTerm));
    $this->assertSame('http://sti-portal.fao.org/page/' . $this->page->uuid(), $this->storedUri('node', $this->page));

    $this->assertSame([], $reminter->verify());

    // Idempotent: a second full run plans and rewrites nothing.
    $this->assertSame([], $reminter->planConfigChanges());
    $this->assertSame(0, $reminter->applyConfigChanges());
    $this->assertSame(0, $this->runRemint($reminter, 100));
  }

  /**
   * A node bundle on bare sti_namespace with no type in the map stops the run.
   */
  public function testUnknownBundleIsRefusedNotGuessed(): void {
    NodeType::create(['type' => 'mystery', 'name' => 'Mystery'])->save();
    $this->map('node_type', 'mystery', 'https://example.org/Mystery', 'sti_namespace');

    $this->expectException(\UnexpectedValueException::class);
    $this->expectExceptionMessage('node:mystery');
    $this->reminter()->planConfigChanges();
  }

  /**
   * A Joinup bundle on w21 that is not in the map stays on w21.
   *
   * Joinup's release bundle is mapped to w21 on the real site. Treating it as
   * ours either aborted the update (the first version of this code) or would
   * have moved Joinup content into the STI namespace.
   */
  public function testJoinupBundleOnW21IsLeftAlone(): void {
    NodeType::create(['type' => 'release', 'name' => 'Release'])->save();
    $release = Node::create(['type' => 'release', 'title' => 'R']);
    $release->save();
    $this->map('node_type', 'release', 'http://www.w3.org/ns/dcat#Dataset', 'joinup_po_namespace:w21');
    $uri = 'http://data.europa.eu/w21/' . $release->uuid();
    $this->row('node', $release, $uri);

    $reminter = $this->reminter();
    $this->assertNotContains('release', array_column($reminter->planConfigChanges(), 'bundle'));
    $reminter->applyConfigChanges();
    $this->assertSame('joinup_po_namespace:w21', $this->plugin('node.type.release'));

    $this->runRemint($reminter, 100);
    $this->assertSame($uri, $this->storedUri('node', $release));
    $this->assertArrayNotHasKey('node:release', $reminter->verify());
  }

  /**
   * Runs every scope to exhaustion and returns the number rewritten.
   */
  private function runRemint(UriReminter $reminter, int $batchSize): int {
    $total = 0;
    foreach ($reminter->scopes() as $scope) {
      $cursor = 0;
      do {
        $result = $reminter->remintBatch($scope, $cursor, $batchSize);
        $cursor = $result['last_id'];
        $total += $result['rewritten'];
      } while ($result['examined'] > 0);
    }
    return $total;
  }

  /**
   * Writes an rdf_sync mapping into raw config, bypassing entity hooks.
   */
  private function map(string $bundleEntityType, string $bundle, string $type, ?string $plugin, ?string $uriField = NULL): void {
    $prefix = $bundleEntityType === 'node_type' ? 'node.type.' : 'taxonomy.vocabulary.';
    $config = $this->config($prefix . $bundle);
    $settings = ['type' => $type, 'uri_plugin' => $plugin, 'fields' => []];
    if ($uriField !== NULL) {
      $settings['uri_field_name'] = $uriField;
    }
    $config->set('third_party_settings.rdf_sync', $settings);
    $dependencies = (array) $config->get('dependencies.module');
    $config->set('dependencies.module', array_values(array_unique([...$dependencies, 'rdf_sync'])));
    $config->save();
    $this->container->get('entity_type.manager')->getStorage($bundleEntityType)->resetCache([$bundle]);
  }

  /**
   * Inserts an rdf_sync_uri row.
   */
  private function row(string $entityType, $entity, string $uri): void {
    $this->database()->insert('rdf_sync_uri')->fields([
      'entity_type' => $entityType,
      'entity_id' => $entity->id(),
      'bundle' => $entity->bundle(),
      'uri' => $uri,
    ])->execute();
  }

  /**
   * Reads the stored URI of an entity.
   */
  private function storedUri(string $entityType, $entity): string {
    return (string) $this->database()->select('rdf_sync_uri', 'r')
      ->fields('r', ['uri'])
      ->condition('entity_type', $entityType)
      ->condition('entity_id', $entity->id())
      ->execute()
      ->fetchField();
  }

  /**
   * Reads term_uri from storage, not from a cached entity.
   */
  private function termUriFieldValue(TermInterface $term): string {
    $storage = $this->container->get('entity_type.manager')->getStorage('taxonomy_term');
    $storage->resetCache([$term->id()]);
    return (string) $storage->load($term->id())->get('term_uri')->value;
  }

  /**
   * Reads the uri_plugin of a bundle from active config.
   */
  private function plugin(string $configName): ?string {
    return $this->config($configName)->get('third_party_settings.rdf_sync.uri_plugin');
  }

  /**
   * Returns a fresh UUID.
   */
  private function uuid(): string {
    return $this->container->get('uuid')->generate();
  }

  /**
   * Returns the reminter service.
   */
  private function reminter(): UriReminter {
    return $this->container->get('sti_rdf.uri_reminter');
  }

  /**
   * Returns the database connection.
   */
  private function database(): Connection {
    return $this->container->get('database');
  }

}
