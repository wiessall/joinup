<?php

declare(strict_types=1);

namespace Drupal\Tests\sti_dedupe\Kernel;

use Drupal\sti_dedupe\DecisionStore;
use Drupal\sti_dedupe\SameAsClaims;
use Drupal\taxonomy\TermInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests sameInnovationAs claims on solutions, and keeping them.
 *
 * Every failure here is silent: a claim that disappears on re-import, or a
 * cluster that the backfill skips, leaves a reviewer's decision invisible on
 * the solutions and in the graph while the site carries on.
 */
#[CoversClass(SameAsClaims::class)]
#[Group('sti_dedupe')]
final class SameAsClaimsTest extends StiDedupeKernelTestBase {

  /**
   * Re-importing a collection keeps its solutions' sameInnovationAs claims.
   *
   * The ird_claims plugin deletes a collection's claims before writing fresh
   * ones; same-as claims are attributed to that collection too, and used to
   * go with them.
   */
  public function testReimportKeepsSameAsClaims(): void {
    $one = $this->collection('One');
    $two = $this->collection('Two');
    $readiness = $this->property('readiness', self::READINESS);
    $a = $this->solution('A', [$this->claim($one, $readiness, 'TRL 5')], $one);
    $b = $this->solution('B', [], $two);
    $this->sameAs()->linkCluster([(int) $a->id(), (int) $b->id()]);

    $a = $this->reimport($a, $one, [$this->claim($one, $readiness, 'TRL 6')]);

    $this->assertSame([(int) $b->id()], $this->sameAs()->objectsOf($this->reload($a)));
    $this->assertSame([], $this->sameAs()->missingPairs([(int) $a->id(), (int) $b->id()]));
    // The imported claim itself was replaced, as before.
    $labels = [];
    foreach ($this->reload($a)->get('field_claims')->referencedEntities() as $claim) {
      if (!$claim->get('field_claim_literal_value')->isEmpty()) {
        $labels[] = $claim->get('field_claim_literal_value')->value;
      }
    }
    $this->assertSame(['TRL 6'], $labels);
  }

  /**
   * Writing a cluster twice adds nothing, and one shared root term is used.
   */
  public function testIdempotentWithOneRootTerm(): void {
    $one = $this->collection('One');
    $two = $this->collection('Two');
    $nids = [
      (int) $this->solution('A', [], $one)->id(),
      (int) $this->solution('B', [], $two)->id(),
      (int) $this->solution('C', [], $two)->id(),
    ];

    $this->assertSame(6, $this->sameAs()->linkCluster($nids), 'Three solutions, two claims each.');
    $this->assertSame(0, $this->sameAs()->linkCluster($nids));
    $this->assertSame([], $this->sameAs()->missingPairs($nids));

    $property = $this->sameAs()->findProperty();
    $this->assertInstanceOf(TermInterface::class, $property);
    $this->assertSame([0], array_map('intval', array_column($property->get('parent')->getValue(), 'target_id')), 'A system predicate sits at the root.');
    $this->assertCount(1, $this->container->get('entity_type.manager')->getStorage('taxonomy_term')->loadByProperties([
      'vid' => 'claim_properties',
      'name' => SameAsClaims::PROPERTY_NAME,
    ]));
  }

  /**
   * A collection column that happens to be called sameInnovationAs is not it.
   */
  public function testCollectionTermWithTheSameNameIsNotTheSystemTerm(): void {
    $imported = $this->property(SameAsClaims::PROPERTY_NAME, NULL);
    $this->assertNull($this->sameAs()->findProperty());
    $this->assertNotSame((int) $imported->id(), (int) $this->sameAs()->property()->id());
  }

  /**
   * The deploy hook writes claims for every recorded "same" cluster.
   *
   * This is the restore path for sites where an earlier sti_dedupe_update_11002
   * deleted the claims and left only the decisions.
   */
  public function testDeployHookRestoresFromDecisions(): void {
    $one = $this->collection('One');
    $a = $this->solution('A', [], $one);
    $b = $this->solution('B', [], $one);
    $c = $this->solution('C', [], $one);
    $d = $this->solution('D', [], $one);
    $e = $this->solution('E', [], $one);
    $decisions = $this->container->get('sti_dedupe.decisions');
    $decisions->record((int) $a->id(), (int) $b->id(), DecisionStore::SAME);
    $decisions->record((int) $b->id(), (int) $c->id(), DecisionStore::SAME);
    $decisions->record((int) $d->id(), (int) $e->id(), DecisionStore::DIFFERENT);

    $this->runDeployHook();

    $abc = [(int) $a->id(), (int) $b->id(), (int) $c->id()];
    $this->assertSame([], $this->sameAs()->missingPairs($abc));
    $this->assertSame([(int) $b->id(), (int) $c->id()], $this->sameAs()->objectsOf($this->reload($a)));
    $this->assertSame([], $this->sameAs()->objectsOf($this->reload($d)), '"Different" writes nothing.');

    // A second run is a no-op.
    $revision = (int) $this->reload($a)->getRevisionId();
    $this->runDeployHook();
    $this->assertSame($revision, (int) $this->reload($a)->getRevisionId());
  }

  /**
   * One broken cluster does not stop the others, and the hook then fails.
   */
  public function testDeployHookFailsLoudlyButCompletesTheRest(): void {
    $one = $this->collection('One');
    $a = $this->solution('A', [], $one);
    $b = $this->solution('B', [], $one);
    $orphan = $this->solution('Orphan', [], $one);
    $orphan->set('og_audience', NULL)->save();
    $c = $this->solution('C', [], $one);
    $decisions = $this->container->get('sti_dedupe.decisions');
    $decisions->record((int) $a->id(), (int) $b->id(), DecisionStore::SAME);
    $decisions->record((int) $orphan->id(), (int) $c->id(), DecisionStore::SAME);

    try {
      $this->runDeployHook();
      $this->fail('A cluster that could not be restored must fail the deploy hook.');
    }
    catch (\RuntimeException $exception) {
      $this->assertStringContainsString($orphan->id() . ',' . $c->id(), $exception->getMessage());
    }
    $this->assertSame([], $this->sameAs()->missingPairs([(int) $a->id(), (int) $b->id()]));
  }

  /**
   * Runs the batched deploy hook to completion.
   */
  private function runDeployHook(): void {
    require_once $this->container->get('extension.list.module')->getPath('sti_dedupe') . '/sti_dedupe.deploy.php';
    $sandbox = [];
    $guard = 0;
    do {
      sti_dedupe_deploy_restore_same_as_claims($sandbox);
    } while (($sandbox['#finished'] ?? 1) < 1 && $guard++ < 100);
  }

  /**
   * Returns the service under test.
   */
  private function sameAs(): SameAsClaims {
    return $this->container->get('sti_dedupe.same_as_claims');
  }

}
