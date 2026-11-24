<?php

declare(strict_types=1);

namespace Drupal\Tests\sti_dedupe\Kernel;

use Drupal\node\NodeInterface;
use Drupal\sti_dedupe\DecisionStore;
use Drupal\sti_dedupe\ReplacementStore;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests decision symmetry, clusters and replacement chains.
 *
 * Clusters decide which solutions end up on one innovation, so a wrong
 * closure links records nobody judged the same, without any error.
 */
#[CoversClass(DecisionStore::class)]
#[CoversClass(ReplacementStore::class)]
#[Group('sti_dedupe')]
final class DecisionStoreTest extends StiDedupeKernelTestBase {

  /**
   * Decisions are symmetric within a collection, separate across them.
   */
  public function testDecisionsArePerCollectionSymmetricAndValidated(): void {
    $store = $this->store();
    $store->record(100, 9, 4, DecisionStore::DIFFERENT);
    $this->assertSame(DecisionStore::DIFFERENT, $store->get(100, 4, 9));
    $store->record(100, 4, 9, DecisionStore::SAME);
    $this->assertSame(DecisionStore::SAME, $store->get(100, 9, 4));
    $this->assertSame([9], $store->decidedWith(100, 4));

    $this->assertNull($store->get(200, 4, 9));
    $this->assertSame([], $store->decidedWith(200, 4));
    $store->record(200, 4, 9, DecisionStore::SAME);
    $this->assertSame([[4, 9]], $store->samePairs(), 'Listed once, whoever decided.');

    foreach ([[1, 1, DecisionStore::SAME], [1, 2, 'same_separate']] as [$a, $b, $decision]) {
      try {
        $store->record(100, $a, $b, $decision);
        $this->fail("record($a, $b, $decision) must be refused.");
      }
      catch (\InvalidArgumentException) {
        $this->addToAssertionCount(1);
      }
    }
  }

  /**
   * Update 11003 copies each old decision to both records' collections.
   */
  public function testUpdateAssignsOldDecisionsToBothCollections(): void {
    $one = $this->collection('One');
    $two = $this->collection('Two');
    $a = $this->solution('A', [], $one);
    $b = $this->solution('B', [], $two);
    $c = $this->solution('C', [], $one);
    $orphan = $this->solution('Orphan', [], $one);
    $orphan->set('og_audience', NULL)->save();
    $orphan2 = $this->solution('Orphan 2', [], $one);
    $orphan2->set('og_audience', NULL)->save();
    $old = static fn(NodeInterface $x, NodeInterface $y, string $decision): array => [
      'collection_nid' => 0,
      'nid_low' => min((int) $x->id(), (int) $y->id()),
      'nid_high' => max((int) $x->id(), (int) $y->id()),
      'decision' => $decision,
      'uid' => 0,
      'created' => 0,
    ];
    $insert = $this->container->get('database')->insert('sti_dedupe_decision')->fields(array_keys($old($a, $b, 'same')));
    $insert->values($old($a, $b, DecisionStore::SAME))->values($old($a, $c, DecisionStore::DIFFERENT))->values($old($orphan, $orphan2, DecisionStore::SAME))->execute();

    \Drupal::moduleHandler()->loadInclude('sti_dedupe', 'install');
    $message = sti_dedupe_update_11003();

    $store = $this->store();
    $this->assertSame(DecisionStore::SAME, $store->get((int) $one->id(), (int) $a->id(), (int) $b->id()));
    $this->assertSame(DecisionStore::SAME, $store->get((int) $two->id(), (int) $a->id(), (int) $b->id()));
    $this->assertSame(DecisionStore::DIFFERENT, $store->get((int) $one->id(), (int) $a->id(), (int) $c->id()));
    $this->assertNull($store->get((int) $two->id(), (int) $a->id(), (int) $c->id()), 'Same-collection pairs are not copied.');
    $this->assertSame(DecisionStore::SAME, $store->get(0, (int) $orphan->id(), (int) $orphan2->id()));
    $this->assertStringContainsString('1 pair(s) have no collection', $message);
  }

  /**
   * Replacement chains resolve to the last survivor and refuse loops.
   */
  public function testReplacementChains(): void {
    $replacements = $this->container->get('sti_dedupe.replacements');
    $replacements->record(10, 11);
    $replacements->record(11, 12);
    $this->assertSame(12, $replacements->survivorOf(10));
    $this->assertNull($replacements->survivorOf(12));
    $this->assertSame([10, 11], $replacements->replacedAmong([10, 11, 12]));

    $replacements->record(12, 10);
    $this->expectException(\RuntimeException::class);
    $replacements->survivorOf(10);
  }

  /**
   * Returns the decision store.
   */
  private function store(): DecisionStore {
    return $this->container->get('sti_dedupe.decisions');
  }

}
