<?php

declare(strict_types=1);

namespace Drupal\Tests\sti_rdf\Kernel;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\sti_rdf\Service\GraphSynchronizer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests writing existing entities to the graph in chunks.
 *
 * What fails silently in a rebuild: an entity skipped because a neighbour in
 * its chunk was rejected, a rejected chunk resent with every later one, or a
 * cursor that skips ids. Each would leave the graph short with no error.
 */
#[CoversClass(GraphSynchronizer::class)]
#[Group('sti_rdf')]
final class GraphSynchronizerTest extends KernelTestBase {

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

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container): void {
    parent::register($container);
    $container->register('rdf_sync.synchronizer', RecordingSynchronizer::class)->setPublic(TRUE);
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installSchema('rdf_sync', ['rdf_sync_uri']);
    RecordingSynchronizer::$writes = [];
    RecordingSynchronizer::$poison = [];

    foreach (['solution', 'page'] as $type) {
      NodeType::create(['type' => $type, 'name' => $type])->save();
    }
    // Five solutions (ids 1-5) and one page (id 6), created before the
    // mapping exists so that saving them syncs nothing.
    foreach (['solution', 'solution', 'solution', 'solution', 'solution', 'page'] as $type) {
      Node::create(['type' => $type, 'title' => $type])->save();
    }
    $this->config('node.type.solution')->set('third_party_settings.rdf_sync', [
      'type' => 'https://sti-portal.fao.org/sti#SourceRecord',
      'uri_plugin' => 'sti_namespace:source-record',
      'fields' => [],
    ])->save();
    $this->container->get('entity_type.manager')->getStorage('node_type')->resetCache();
    RecordingSynchronizer::$writes = [];
  }

  /**
   * Chunks follow the ids in order, and unmapped bundles are left out.
   */
  public function testChunksCoverEveryMappedEntityOnce(): void {
    $this->assertSame(6, $this->sync()->count('node'));
    $this->assertSame(5, $this->sync()->count('node', ['solution']));

    $summary = $this->runAll('node', [], 2);

    $this->assertSame([[1, 2], [3, 4], [5]], RecordingSynchronizer::$writes, 'The page (id 6) is not mapped.');
    $this->assertSame(['examined' => 6, 'synced' => 5, 'failed' => []], $summary);
  }

  /**
   * One rejected entity costs only itself and is named.
   */
  public function testRejectedEntityIsIsolated(): void {
    RecordingSynchronizer::$poison = [3];

    $summary = $this->runAll('node', ['solution'], 2);

    $this->assertSame([[1, 2], [4], [5]], RecordingSynchronizer::$writes, 'Its chunk neighbour (4) is still written.');
    $this->assertSame([3], array_keys($summary['failed']));
    $this->assertStringContainsString('Store rejected 3', $summary['failed'][3]);
    $this->assertSame(4, $summary['synced']);
    $this->assertSame(0, $this->container->get('rdf_sync.synchronizer')->pending(), 'A failed chunk is not left stacked to be resent.');
  }

  /**
   * A run can resume after an id.
   */
  public function testResumeAfterId(): void {
    $result = $this->sync()->syncChunk('node', ['solution'], 3, 50);

    $this->assertSame([[4, 5]], RecordingSynchronizer::$writes);
    $this->assertSame(5, $result['last_id']);
    $this->assertSame(0, $this->sync()->syncChunk('node', ['solution'], 5, 50)['examined']);
  }

  /**
   * Runs chunks to exhaustion.
   *
   * @return array{examined: int, synced: int, failed: array<int, string>}
   *   Totals over the run.
   */
  private function runAll(string $entityTypeId, array $bundles, int $chunk): array {
    $totals = ['examined' => 0, 'synced' => 0, 'failed' => []];
    $cursor = 0;
    do {
      $result = $this->sync()->syncChunk($entityTypeId, $bundles, $cursor, $chunk);
      $cursor = $result['last_id'];
      $totals['examined'] += $result['examined'];
      $totals['synced'] += $result['synced'];
      $totals['failed'] += $result['failed'];
    } while ($result['examined'] > 0);
    return $totals;
  }

  /**
   * Returns the service under test.
   */
  private function sync(): GraphSynchronizer {
    return $this->container->get('sti_rdf.graph_synchronizer');
  }

}
