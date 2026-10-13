<?php

declare(strict_types=1);

namespace Drupal\Tests\sti_dedupe\Kernel;

use Drupal\field\Entity\FieldConfig;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\sti_dedupe\ContentModel;
use Drupal\sti_dedupe\DecisionStore;
use Drupal\taxonomy\Entity\Term;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests sti_dedupe_update_11002(): legacy sameInnovationAs claims.
 *
 * The update records each legacy claim as a "same" decision and keeps the
 * claim: "same" lives on the solutions. Only URL-literal fallback claims are
 * removed, and the deploy hook writes proper ones in their place. The silent
 * failure guarded here is the one an earlier revision of this update had:
 * deleting the reviewer's links from the solutions.
 */
#[Group('sti_dedupe')]
final class LegacySameAsConversionTest extends StiDedupeKernelTestBase {

  /**
   * Reference claims stay; literal ones are recorded, removed and rewritten.
   */
  public function testClaimsAreRecordedAndKept(): void {
    // The first version created the term at the root of claim_properties.
    $sameAs = Term::create(['vid' => ContentModel::CLAIM_PROPERTIES, 'name' => 'sameInnovationAs']);
    $sameAs->save();

    $one = $this->collection('One');
    $two = $this->collection('Two');
    $readiness = $this->property('readiness', self::READINESS);
    $a = $this->solution('A', [$this->claim($one, $readiness, 'TRL 5')], $one);
    $b = $this->solution('B', [], $two);
    $c = $this->solution('C', [], $two);

    // What the first version wrote for "same, keep both" on A and B.
    $a->get(ContentModel::CLAIMS)->appendItem(Paragraph::create([
      'type' => ContentModel::CLAIM,
      ContentModel::CLAIMED_BY => $one->id(),
      ContentModel::CLAIM_PROPERTY => $sameAs->id(),
      ContentModel::CLAIM_REF_NODE => $b->id(),
    ]));
    $a->set(ContentModel::RELATED_SOLUTIONS, [$b->id()]);
    $a->save();
    // Its fallback when field_claim_ref_node was missing: a URL literal.
    $b->get(ContentModel::CLAIMS)->appendItem(Paragraph::create([
      'type' => ContentModel::CLAIM,
      ContentModel::CLAIMED_BY => $two->id(),
      ContentModel::CLAIM_PROPERTY => $sameAs->id(),
      ContentModel::CLAIM_LITERAL => 'http://localhost/node/' . $c->id(),
    ]));
    $b->save();

    $this->runUpdate();

    $decisions = $this->container->get('sti_dedupe.decisions');
    $this->assertSame(DecisionStore::SAME, $decisions->get((int) $a->id(), (int) $b->id()));
    $this->assertSame(DecisionStore::SAME, $decisions->get((int) $b->id(), (int) $c->id()));

    $sameAsClaims = $this->container->get('sti_dedupe.same_as_claims');
    $this->assertSame((int) $sameAs->id(), (int) $sameAsClaims->findProperty()?->id(), 'The legacy root term is reused.');
    $this->assertSame([(int) $b->id()], $sameAsClaims->objectsOf($this->reload($a)), 'The reference claim stays.');
    $this->assertCount(2, $this->reload($a)->get(ContentModel::CLAIMS), 'Nothing else on A is touched.');
    $this->assertSame([(int) $b->id()], array_map('intval', array_column($this->reload($a)->get(ContentModel::RELATED_SOLUTIONS)->getValue(), 'target_id')));
    $this->assertCount(0, $this->reload($b)->get(ContentModel::CLAIMS), 'The literal fallback is removed.');
    $this->assertNotNull(FieldConfig::loadByName('paragraph', ContentModel::CLAIM, ContentModel::CLAIM_REF_NODE));
  }

  /**
   * A site that never had the legacy claims passes straight through.
   */
  public function testNothingToConvert(): void {
    $this->runUpdate();
    $this->assertNull($this->container->get('sti_dedupe.same_as_claims')->findProperty());
  }

  /**
   * Runs the batched update to completion.
   */
  private function runUpdate(): void {
    $this->container->get('module_handler')->loadInclude('sti_dedupe', 'install');
    $sandbox = [];
    $guard = 0;
    do {
      sti_dedupe_update_11002($sandbox);
    } while (($sandbox['#finished'] ?? 1) < 1 && $guard++ < 100);
    $this->assertSame(1, $sandbox['#finished']);
  }

}
