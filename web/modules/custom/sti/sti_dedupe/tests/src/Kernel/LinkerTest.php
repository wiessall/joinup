<?php

declare(strict_types=1);

namespace Drupal\Tests\sti_dedupe\Kernel;

use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\sti_dedupe\DecisionStore;
use Drupal\sti_dedupe\Linker;
use Drupal\sti_rdf\Event\UriResolveEvent;
use Drupal\sti_rdf\StiUri;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests collection decisions and curator merges.
 *
 * Every invariant here fails silently when broken:
 * - "same" writes one claim, on the deciding collection's record, credited
 *   to that collection; the other record is never written;
 * - a collection cannot write on another collection's record;
 * - a decision touches no ATIO innovation;
 * - decisions are per collection;
 * - a curator merge takes over the losers' sources only, and retires the
 *   losers without deleting them.
 */
#[CoversClass(Linker::class)]
#[Group('sti_dedupe')]
final class LinkerTest extends StiDedupeKernelTestBase {

  /**
   * "Same" is one claim on the subject; the object is untouched.
   */
  public function testSameWritesOneClaimOnTheSubjectOnly(): void {
    $one = $this->collection('One');
    $two = $this->collection('Two');
    $a = $this->solution('A', [], $one);
    $b = $this->solution('B', [], $two);
    $revision = (int) $b->getRevisionId();

    $this->linker()->decideSame($one, $a, $b);

    $this->assertSame([(int) $b->id() => (int) $one->id()], $this->sameAsClaims($a));
    $this->assertSame([], $this->sameAsClaims($b));
    $this->assertSame($revision, (int) $this->reload($b)->getRevisionId(), 'The other record was not saved.');
    $this->assertSame(DecisionStore::SAME, $this->decisions()->get((int) $one->id(), (int) $a->id(), (int) $b->id()));
    $this->assertNull($this->decisions()->get((int) $two->id(), (int) $a->id(), (int) $b->id()));
  }

  /**
   * A record outside the deciding collection is refused, before any write.
   */
  public function testSubjectOutsideCollectionIsRefused(): void {
    $one = $this->collection('One');
    $two = $this->collection('Two');
    $a = $this->solution('A', [], $one);
    $b = $this->solution('B', [], $two);

    foreach (['decideSame', 'decideDifferent'] as $method) {
      try {
        $this->linker()->$method($one, $b, $a);
        $this->fail("$method on another collection's record must be refused.");
      }
      catch (\InvalidArgumentException) {
      }
    }
    $this->assertSame([], $this->sameAsClaims($b));
    $this->assertNull($this->decisions()->get((int) $one->id(), (int) $a->id(), (int) $b->id()));
  }

  /**
   * A decision does not change any innovation's sources or revision.
   */
  public function testSameLeavesInnovationsUntouched(): void {
    $one = $this->collection('One');
    $a = $this->solution('A', [], $one);
    $b = $this->solution('B', [], $this->collection('Two'));
    $innovation = $this->innovation('I', [$b]);
    $revision = (int) $innovation->getRevisionId();

    $this->linker()->decideSame($one, $a, $b);

    $this->assertSame([(int) $b->id()], $this->sources($innovation));
    $this->assertSame($revision, (int) $this->reload($innovation)->getRevisionId());
  }

  /**
   * One collection's decision does not decide the pair for the other.
   */
  public function testDecisionsArePerCollection(): void {
    $one = $this->collection('One');
    $two = $this->collection('Two');
    $a = $this->solution('A', [], $one);
    $b = $this->solution('B', [], $two);

    $this->linker()->decideDifferent($one, $a, $b);
    $this->assertSame([], $this->decisions()->decidedWith((int) $two->id(), (int) $b->id()), 'Still on offer to Two.');

    // Two may still decide "same" for its own record.
    $this->linker()->decideSame($two, $b, $a);
    $this->assertSame([(int) $a->id() => (int) $two->id()], $this->sameAsClaims($b));
    $this->assertSame([], $this->sameAsClaims($a));
  }

  /**
   * A collection's "same" is not silently overturned by its own "different".
   */
  public function testDifferentCannotOverturnSame(): void {
    $one = $this->collection('One');
    $a = $this->solution('A', [], $one);
    $b = $this->solution('B', [], $one);
    $this->linker()->decideSame($one, $a, $b);

    $this->expectException(\LogicException::class);
    $this->linker()->decideDifferent($one, $b, $a);
  }

  /**
   * "Same" pairs on different innovations are listed for curators.
   */
  public function testPairsToReconcile(): void {
    $one = $this->collection('One');
    $a = $this->solution('A', [], $one);
    $b = $this->solution('B', [], $this->collection('Two'));
    $c = $this->solution('C', [], $one);
    $x = $this->innovation('X', [$a]);
    $y = $this->innovation('Y', [$b]);
    $this->linker()->decideSame($one, $a, $b);
    // On no innovation: nothing to reconcile.
    $this->linker()->decideSame($one, $c, $b);

    $this->assertSame([
      ['solutions' => [(int) $a->id(), (int) $b->id()], 'innovations' => [(int) $x->id(), (int) $y->id()]],
    ], $this->linker()->pairsToReconcile());
  }

  /**
   * A merge takes over the losers' sources only; losers are retired.
   */
  public function testMergeTakesOverSourcesAndRetiresLosers(): void {
    $a = $this->solution('A');
    $b = $this->solution('B');
    $c = $this->solution('C');
    $unrelated = $this->solution('Unrelated');
    $x = $this->innovation('X', [$a]);
    $y = $this->innovation('Y', [$b, $c]);

    $this->linker()->mergeInnovations((int) $x->id(), [(int) $y->id()]);

    $this->assertSame([(int) $a->id(), (int) $b->id(), (int) $c->id()], $this->sources($x));
    $this->assertNotContains((int) $unrelated->id(), $this->sources($x));
    $loser = $this->reload($y);
    $this->assertFalse($loser->isPublished());
    $this->assertSame([(int) $b->id(), (int) $c->id()], $this->sources($y), 'The loser keeps its own record of sources.');
    $this->assertSame([(int) $x->id()], $this->container->get('sti_dedupe.innovation_lookup')->derivedFrom([(int) $c->id()]));
    $this->assertCount(4, Node::loadMultiple([$a->id(), $b->id(), $c->id(), $unrelated->id()]), 'Nothing was deleted.');

    $event = new UriResolveEvent(StiUri::forEntity('innovation', $y->uuid()));
    $this->container->get('event_dispatcher')->dispatch($event);
    $this->assertSame(StiUri::forEntity('innovation', $x->uuid()), $event->getReplacementUri());
  }

  /**
   * A refused claim leaves no decision behind.
   */
  public function testFailedClaimRollsBackTheDecision(): void {
    $one = $this->collection('One');
    $a = $this->solution('A', [], $one);
    $b = $this->solution('B', [], $one);
    // Breaks the claim write after the decision was recorded.
    $this->container->get('entity_type.manager')->getStorage('field_config')->load('paragraph.claims.field_claim_ref_node')->delete();

    try {
      $this->linker()->decideSame($one, $a, $b);
      $this->fail('A claim without its object field cannot be written.');
    }
    catch (\LogicException) {
    }
    $this->assertNull($this->decisions()->get((int) $one->id(), (int) $a->id(), (int) $b->id()));
  }

  /**
   * Returns a solution's sameInnovationAs claims as [object => claimed by].
   *
   * @return array<int, int>
   *   Claimed-by collection id, keyed by the solution named, ascending.
   */
  private function sameAsClaims(NodeInterface $solution): array {
    $property = $this->container->get('sti_dedupe.same_as_claims')->findProperty();
    $out = [];
    foreach ($this->reload($solution)->get('field_claims')->referencedEntities() as $claim) {
      if ($property !== NULL && (int) $claim->get('field_claim_property')->target_id === (int) $property->id()) {
        $out[(int) $claim->get('field_claim_ref_node')->target_id] = (int) $claim->get('field_claimed_by')->target_id;
      }
    }
    ksort($out);
    return $out;
  }

  /**
   * Returns the linker.
   */
  private function linker(): Linker {
    return $this->container->get('sti_dedupe.linker');
  }

  /**
   * Returns the decision store.
   */
  private function decisions(): DecisionStore {
    return $this->container->get('sti_dedupe.decisions');
  }

}
