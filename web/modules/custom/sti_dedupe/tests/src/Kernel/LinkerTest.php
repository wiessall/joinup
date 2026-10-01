<?php

declare(strict_types=1);

namespace Drupal\Tests\sti_dedupe\Kernel;

use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\sti_dedupe\DecisionStore;
use Drupal\sti_dedupe\Exception\SurvivorRequiredException;
use Drupal\sti_dedupe\Linker;
use Drupal\sti_rdf\Event\UriResolveEvent;
use Drupal\sti_rdf\StiUri;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests "same" and "different" decisions on pairs of solutions.
 *
 * The invariants are the ones whose failure is silent:
 * - a solution (a source record) is never merged or deleted; "same" is a
 *   sameInnovationAs claim on each solution of the cluster, naming every
 *   other one and attributed to that solution's own collection;
 * - a cluster decided "same", directly or transitively, ends up on one
 *   innovation, each source pinned at the revision carrying its claims;
 * - joining two innovations needs a survivor, and without one nothing at
 *   all is written, the decision and the claims included;
 * - the loser is unpublished, not deleted, recorded as replaced, excluded
 *   from lookups, and its URI resolves to the survivor.
 */
#[CoversClass(Linker::class)]
#[Group('sti_dedupe')]
final class LinkerTest extends StiDedupeKernelTestBase {

  /**
   * With no innovation anywhere, the decision and the claims are written.
   */
  public function testNoInnovationWritesDecisionAndClaims(): void {
    $one = $this->collection('One');
    $two = $this->collection('Two');
    $a = $this->solution('Solar pump A', [], $one);
    $b = $this->solution('Solar pump B', [], $two);

    $result = $this->linker()->linkSame($a, $b);

    $this->assertNull($result->innovation);
    $this->assertSame(DecisionStore::SAME, $this->decisions()->get((int) $a->id(), (int) $b->id()));
    $this->assertSame([], $this->innovationIds());
    $this->assertSame([(int) $b->id() => (int) $one->id()], $this->sameAsClaims($a), 'A names B, claimed by its own collection.');
    $this->assertSame([(int) $a->id() => (int) $two->id()], $this->sameAsClaims($b), 'B names A, claimed by its own collection.');
    $this->assertSame([(int) $b->id()], array_map('intval', array_column($this->reload($a)->get('related_solutions')->getValue(), 'target_id')));
  }

  /**
   * One innovation receives the other solution, pinned at its revision.
   */
  public function testOneInnovationReceivesTheCluster(): void {
    $a = $this->solution('Solar pump A');
    $b = $this->solution('Solar pump B');
    $innovation = $this->innovation('Solar pump', [$a]);
    $before = (int) $innovation->getRevisionId();

    $result = $this->linker()->linkSame($b, $a);

    $this->assertSame((int) $innovation->id(), $result->innovation);
    $this->assertSame([(int) $b->id()], $result->added);
    $this->assertSame([(int) $a->id(), (int) $b->id()], $this->sources($innovation));

    $reloaded = $this->reload($innovation);
    $this->assertGreaterThan($before, (int) $reloaded->getRevisionId(), 'Linking is an editorial change and gets a revision.');
    $pinned = array_column($reloaded->get('atio_derived_from')->getValue(), 'target_revision_id', 'target_id');
    $this->assertSame((int) $this->reload($b)->getRevisionId(), (int) $pinned[$b->id()], 'Pinned at the revision that carries the sameInnovationAs claim.');
    $this->assertArrayHasKey((int) $a->id(), $this->sameAsClaims($b));
  }

  /**
   * Earlier "same" decisions are followed: the whole cluster comes along.
   */
  public function testTransitiveClusterComesAlong(): void {
    $a = $this->solution('A');
    $b = $this->solution('B');
    $c = $this->solution('C');
    $this->linker()->linkSame($a, $b);
    $innovation = $this->innovation('Innovation', [$c]);

    $result = $this->linker()->linkSame($b, $c);

    $this->assertSame([(int) $a->id(), (int) $b->id(), (int) $c->id()], $result->cluster);
    $this->assertSame([(int) $a->id(), (int) $b->id(), (int) $c->id()], $this->sources($innovation));
    // A was never paired with C directly, but the cluster is complete.
    $this->assertSame([(int) $b->id(), (int) $c->id()], array_keys($this->sameAsClaims($a)));
    $this->assertSame([(int) $a->id(), (int) $b->id()], array_keys($this->sameAsClaims($c)));
  }

  /**
   * Two innovations without a survivor: refused, and nothing is written.
   */
  public function testTwoInnovationsWithoutSurvivorWriteNothing(): void {
    $a = $this->solution('A');
    $b = $this->solution('B');
    $x = $this->innovation('X', [$a]);
    $y = $this->innovation('Y', [$b]);
    $revisions = [$x->getRevisionId(), $y->getRevisionId()];

    try {
      $this->linker()->linkSame($a, $b);
      $this->fail('Linking across two innovations without a survivor must be refused.');
    }
    catch (SurvivorRequiredException $e) {
      $this->assertEqualsCanonicalizing([(int) $x->id(), (int) $y->id()], $e->candidates);
    }

    $this->assertNull($this->decisions()->get((int) $a->id(), (int) $b->id()), 'Nothing is written: no decision.');
    $this->assertSame([], $this->sameAsClaims($a), 'Nothing is written: no claim.');
    $this->assertSame([], $this->sameAsClaims($b));
    $this->assertSame($revisions, [$this->reload($x)->getRevisionId(), $this->reload($y)->getRevisionId()]);
    $this->assertTrue($this->reload($y)->isPublished());
  }

  /**
   * The survivor takes everything; the loser is retired, never deleted.
   */
  public function testSurvivorTakesOverAndLoserIsRetired(): void {
    $a = $this->solution('A');
    $b = $this->solution('B');
    $c = $this->solution('C');
    $x = $this->innovation('X', [$a]);
    $y = $this->innovation('Y', [$b, $c]);

    $result = $this->linker()->linkSame($a, $b, (int) $x->id());

    $this->assertSame((int) $x->id(), $result->innovation);
    $this->assertSame([(int) $y->id()], $result->replaced);
    // The survivor gains the pair and every source the loser had.
    $this->assertSame([(int) $a->id(), (int) $b->id(), (int) $c->id()], $this->sources($x));

    $loser = $this->reload($y);
    $this->assertFalse($loser->isPublished());
    $this->assertSame([(int) $b->id(), (int) $c->id()], $this->sources($y), 'The loser keeps its own record of sources.');
    $this->assertSame((int) $x->id(), $this->container->get('sti_dedupe.replacements')->survivorOf((int) $y->id()));
    $this->assertSame([(int) $x->id()], $this->container->get('sti_dedupe.innovation_lookup')->derivedFrom([(int) $c->id()]));

    // Nothing was deleted.
    $this->assertCount(3, Node::loadMultiple([$a->id(), $b->id(), $c->id()]));

    // The loser's URI now resolves to the survivor.
    $event = new UriResolveEvent(StiUri::forEntity('innovation', $y->uuid()));
    $this->container->get('event_dispatcher')->dispatch($event);
    $this->assertSame(StiUri::forEntity('innovation', $x->uuid()), $event->getReplacementUri());
  }

  /**
   * Records "different", which can never silently overturn "same".
   */
  public function testDifferentIsRecordedButCannotOverturnSame(): void {
    $a = $this->solution('A');
    $b = $this->solution('B');
    $c = $this->solution('C');

    $this->linker()->markDifferent($a, $b);
    $this->assertSame(DecisionStore::DIFFERENT, $this->decisions()->get((int) $b->id(), (int) $a->id()));
    $this->assertSame([(int) $a->id()], $this->decisions()->cluster((int) $a->id()), '"Different" never joins a cluster.');

    $this->linker()->linkSame($a, $c);
    $this->expectException(\LogicException::class);
    $this->linker()->markDifferent($c, $a);
  }

  /**
   * Linking refreshes claim conflicts on the innovation straight away.
   */
  public function testLinkingSurfacesConflicts(): void {
    $one = $this->collection('One');
    $two = $this->collection('Two');
    $a = $this->solution('A', [$this->claim($one, $this->property('readiness', self::READINESS), 'TRL 5')]);
    $b = $this->solution('B', [$this->claim($two, $this->property('maturity', self::READINESS), 'TRL 7')]);
    $innovation = $this->innovation('Innovation', [$a]);
    $this->assertSame([], $this->resolutions($innovation));

    $result = $this->linker()->linkSame($a, $b);

    $this->assertTrue($result->conflictsRefreshed);
    $this->assertArrayHasKey(self::READINESS, $this->resolutions($innovation));
  }

  /**
   * A failed check rolls back the decision and the claims alike.
   */
  public function testFailedCheckRollsBackClaims(): void {
    $a = $this->solution('A');
    $b = $this->solution('B');
    // No collection to attribute B's claim to: refused before any write.
    $b->set('og_audience', NULL)->save();

    try {
      $this->linker()->linkSame($a, $b);
      $this->fail('A solution without a collection cannot carry a claim.');
    }
    catch (\LogicException) {
    }

    $this->assertNull($this->decisions()->get((int) $a->id(), (int) $b->id()));
    $this->assertSame([], $this->sameAsClaims($a), 'A is not left half-linked.');
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
   * Returns the ids of every innovation node.
   *
   * @return int[]
   *   Node ids.
   */
  private function innovationIds(): array {
    return array_map('intval', array_values($this->container->get('entity_type.manager')->getStorage('node')->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'atio_innovation')
      ->execute()));
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
