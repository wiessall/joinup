<?php

declare(strict_types=1);

namespace Drupal\Tests\sti_dedupe\Kernel;

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
   * Decisions are symmetric, replaceable, and validated.
   */
  public function testDecisionsAreSymmetricAndValidated(): void {
    $store = $this->store();
    $store->record(9, 4, DecisionStore::DIFFERENT);
    $this->assertSame(DecisionStore::DIFFERENT, $store->get(4, 9));
    $store->record(4, 9, DecisionStore::SAME);
    $this->assertSame(DecisionStore::SAME, $store->get(9, 4));
    $this->assertSame([9], $store->decidedWith(4));

    foreach ([[1, 1, DecisionStore::SAME], [1, 2, 'same_separate']] as [$a, $b, $decision]) {
      try {
        $store->record($a, $b, $decision);
        $this->fail("record($a, $b, $decision) must be refused.");
      }
      catch (\InvalidArgumentException) {
        $this->addToAssertionCount(1);
      }
    }
  }

  /**
   * The cluster follows "same" (and legacy "merged") links, never "different".
   */
  public function testClusterIsTheTransitiveClosureOfSame(): void {
    $store = $this->store();
    $store->record(1, 2, DecisionStore::SAME);
    $store->record(2, 3, DecisionStore::SAME);
    $store->record(3, 4, DecisionStore::DIFFERENT);
    $store->record(5, 6, DecisionStore::SAME);
    $this->container->get('database')->insert('sti_dedupe_decision')
      ->fields(['nid_low' => 6, 'nid_high' => 7, 'decision' => DecisionStore::LEGACY_MERGED, 'uid' => 0, 'created' => 0])
      ->execute();

    $this->assertSame([1, 2, 3], $store->cluster(3));
    $this->assertSame([1, 2, 3], $store->cluster(1));
    $this->assertSame([4], $store->cluster(4));
    $this->assertSame([5, 6, 7], $store->cluster(5));
    $this->assertSame([1, 2, 3, 5, 6, 7], $store->cluster(2, 7));
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
