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
 * A one-off data migration is exactly the kind of code that fails quietly:
 * a claim left behind keeps projecting a statement nobody made, and a field
 * removed too early loses the only record of a reviewer's decision.
 */
#[Group('sti_dedupe')]
final class LegacySameAsConversionTest extends StiDedupeKernelTestBase {

  /**
   * Each legacy claim becomes a decision, and only those claims go.
   */
  public function testClaimsBecomeDecisions(): void {
    $this->field('paragraph', ContentModel::CLAIM, ContentModel::CLAIM_REF_NODE_LEGACY, 'entity_reference', ['target_type' => 'node']);
    $sameAs = Term::create(['vid' => ContentModel::CLAIM_PROPERTIES, 'name' => 'sameInnovationAs']);
    $sameAs->save();

    $one = $this->collection('One');
    $two = $this->collection('Two');
    $readiness = $this->property('readiness', self::READINESS);
    $a = $this->solution('A', [$this->claim($one, $readiness, 'TRL 5')]);
    $b = $this->solution('B');
    $c = $this->solution('C');

    // What the first version wrote for "same, keep both" on A and B: a claim
    // on each side plus a related_solutions mirror.
    $a->get(ContentModel::CLAIMS)->appendItem(Paragraph::create([
      'type' => ContentModel::CLAIM,
      ContentModel::CLAIMED_BY => $one->id(),
      ContentModel::CLAIM_PROPERTY => $sameAs->id(),
      ContentModel::CLAIM_REF_NODE_LEGACY => $b->id(),
    ]));
    $a->set(ContentModel::RELATED_SOLUTIONS, [$b->id(), $c->id()]);
    $a->save();
    // Its fallback, when field_claim_ref_node was missing: a URL literal.
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

    $a = $this->reload($a);
    $this->assertCount(1, $a->get(ContentModel::CLAIMS), 'The ordinary claim stays.');
    $this->assertSame((int) $readiness->id(), (int) $a->get(ContentModel::CLAIMS)->entity->get(ContentModel::CLAIM_PROPERTY)->target_id);
    $this->assertSame([(int) $c->id()], array_map('intval', array_column($a->get(ContentModel::RELATED_SOLUTIONS)->getValue(), 'target_id')), 'Only the mirrored link is removed.');
    $this->assertCount(0, $this->reload($b)->get(ContentModel::CLAIMS));

    $this->assertNull(Term::load($sameAs->id()));
    $this->assertNull(FieldConfig::loadByName('paragraph', ContentModel::CLAIM, ContentModel::CLAIM_REF_NODE_LEGACY));
    $this->assertSame(0, (int) $this->container->get('entity_type.manager')->getStorage('paragraph')->getQuery()
      ->accessCheck(FALSE)
      ->condition(ContentModel::CLAIM_PROPERTY, $sameAs->id())
      ->count()
      ->execute());
  }

  /**
   * A site that never had the legacy claims passes straight through.
   */
  public function testNothingToConvert(): void {
    $this->runUpdate();
    $this->assertNull(FieldConfig::loadByName('paragraph', ContentModel::CLAIM, ContentModel::CLAIM_REF_NODE_LEGACY));
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
