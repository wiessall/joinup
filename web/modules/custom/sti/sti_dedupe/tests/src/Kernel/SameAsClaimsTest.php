<?php

declare(strict_types=1);

namespace Drupal\Tests\sti_dedupe\Kernel;

use Drupal\sti_dedupe\SameAsClaims;
use Drupal\taxonomy\TermInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests sameInnovationAs claims on solutions: writing, keeping, reading.
 *
 * Every failure here is silent: a claim that disappears on re-import, or a
 * lookup that misses one direction, leaves a decision invisible on the
 * solutions, in the graph and to ATIO curators while the site carries on.
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
    $this->sameAs()->claim($a, $b);

    $a = $this->reimport($a, $one, [$this->claim($one, $readiness, 'TRL 6')]);

    $this->assertSame([(int) $b->id()], $this->sameAs()->objectsOf($this->reload($a)));
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
   * Writing a claim twice adds nothing, and one shared root term is used.
   */
  public function testIdempotentWithOneRootTerm(): void {
    $a = $this->solution('A', [], $this->collection('One'));
    $b = $this->solution('B', [], $this->collection('Two'));

    $this->assertTrue($this->sameAs()->claim($a, $b));
    $this->assertFalse($this->sameAs()->claim($this->reload($a), $b));
    $this->assertSame([(int) $b->id()], $this->sameAs()->objectsOf($this->reload($a)));

    $property = $this->sameAs()->findProperty();
    $this->assertInstanceOf(TermInterface::class, $property);
    $this->assertSame([0], array_map('intval', array_column($property->get('parent')->getValue(), 'target_id')), 'A system predicate sits at the root.');
    $this->assertCount(1, $this->container->get('entity_type.manager')->getStorage('taxonomy_term')->loadByProperties([
      'vid' => 'claim_properties',
      'name' => SameAsClaims::PROPERTY_NAME,
    ]));
  }

  /**
   * The lookup follows claims in both directions, one hop.
   */
  public function testLinkedToFollowsBothDirectionsOneHop(): void {
    $one = $this->collection('One');
    $source = $this->solution('Source', [], $one);
    $claimed = $this->solution('Claimed by source', [], $one);
    $claiming = $this->solution('Claims source', [], $this->collection('Two'));
    $twoHops = $this->solution('Two hops away', [], $one);
    $this->sameAs()->claim($source, $claimed);
    $this->sameAs()->claim($claiming, $source);
    $this->sameAs()->claim($claimed, $twoHops);
    // A claim naming the source under another property does not count.
    $otherClaim = $this->claim($one, $this->property('related', NULL), 'x');
    $otherClaim->set('field_claim_ref_node', $source->id());
    $other = $this->solution('Other property', [$otherClaim], $one);

    $expected = [(int) $claimed->id(), (int) $claiming->id()];
    sort($expected);
    $this->assertSame($expected, $this->sameAs()->linkedTo([(int) $source->id()]));
    $this->assertNotContains((int) $other->id(), $this->sameAs()->linkedTo([(int) $source->id()]));
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
   * Returns the service under test.
   */
  private function sameAs(): SameAsClaims {
    return $this->container->get('sti_dedupe.same_as_claims');
  }

}
