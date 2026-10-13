<?php

declare(strict_types=1);

namespace Drupal\Tests\sti_dedupe\Kernel;

use Drupal\node\NodeInterface;
use Drupal\sti_dedupe\Claims\ResolutionReconciler;
use Drupal\sti_dedupe\Claims\ResolutionWriter;
use Drupal\sti_dedupe\ContentModel;
use Drupal\sti_dedupe\Exception\StaleResolutionException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests detection of, and rulings on, mutually exclusive claims.
 *
 * The invariants whose failure is silent:
 * - only mapped, single-valued, non-narrative properties are compared, and
 *   values that mean the same thing do not conflict;
 * - a curator's ruling survives a re-import that recreates identical claim
 *   paragraphs, because verdicts identify claims by value, not by id;
 * - when claims do change, the ruling is kept and flagged stale, never
 *   overwritten or dropped;
 * - an untouched pending resolution follows the claims and disappears when
 *   the sources come to agree;
 * - refreshing is idempotent;
 * - a ruling needs exactly one authoritative value, on the values claimed
 *   now.
 */
#[CoversClass(ResolutionReconciler::class)]
#[CoversClass(ResolutionWriter::class)]
#[Group('sti_dedupe')]
final class ClaimResolutionTest extends StiDedupeKernelTestBase {

  /**
   * The first collection.
   */
  private NodeInterface $one;

  /**
   * The second collection.
   */
  private NodeInterface $two;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->one = $this->collection('One');
    $this->two = $this->collection('Two');
  }

  /**
   * Disagreement on a single-valued property opens a pending resolution.
   */
  public function testDisagreementOpensPendingResolution(): void {
    $innovation = $this->conflictingInnovation('TRL 5', 'TRL 7');

    $result = $this->reconciler()->refresh($innovation);

    $this->assertSame(1, $result->created);
    $resolutions = $this->resolutions($innovation);
    $this->assertSame([self::READINESS], array_keys($resolutions));
    $resolution = $resolutions[self::READINESS];
    $this->assertSame(ContentModel::STATUS_PENDING, $resolution->get(ContentModel::RESOLUTION_STATUS)->value);
    $this->assertSame([
      'TRL 5' => [ContentModel::UNREVIEWED, TRUE],
      'TRL 7' => [ContentModel::UNREVIEWED, TRUE],
    ], $this->verdicts($resolution));
  }

  /**
   * Values that mean the same thing are not a conflict.
   */
  public function testAgreementDoesNotConflict(): void {
    // Same label, written differently.
    $byLabel = $this->conflictingInnovation('TRL 5', 'trl-5');
    $this->assertFalse($this->reconciler()->refresh($byLabel)->changed());

    // Different labels, same exact match.
    $concept = 'https://example.org/readiness/5';
    $a = $this->solution('A', [$this->claim($this->one, $this->property('readiness', self::READINESS), $this->value('Level 5', $concept))]);
    $b = $this->solution('B', [$this->claim($this->two, $this->property('maturity', self::READINESS), $this->value('Pilot', $concept))]);
    $byMatch = $this->innovation('By match', [$a, $b]);
    $this->assertFalse($this->reconciler()->refresh($byMatch)->changed());
    $this->assertSame([], $this->resolutions($byMatch));
  }

  /**
   * Unmapped, multi-valued and narrative properties are never compared.
   */
  public function testOnlyMappedSingleValuedFactsAreCompared(): void {
    $a = $this->solution('A', [
      $this->claim($this->one, $this->property('stage', NULL), 'Pilot'),
      $this->claim($this->one, $this->property('type', self::INNOVATION_TYPE), 'Irrigation'),
      $this->claim($this->one, $this->property('summary', self::SHORT_DESCRIPTION), 'Pumps water.'),
      $this->claim($this->one, $this->property('since', self::START_YEAR), '2019'),
    ]);
    $b = $this->solution('B', [
      $this->claim($this->two, $this->property('stage', NULL), 'Scaling'),
      $this->claim($this->two, $this->property('type', self::INNOVATION_TYPE), 'Energy'),
      $this->claim($this->two, $this->property('summary', self::SHORT_DESCRIPTION), 'A solar pump.'),
      $this->claim($this->two, $this->property('since', self::START_YEAR), '2021'),
    ]);
    $innovation = $this->innovation('Mixed', [$a, $b]);

    $this->reconciler()->refresh($innovation);

    $this->assertSame([self::START_YEAR], array_keys($this->resolutions($innovation)));
  }

  /**
   * A re-import with identical values leaves a ruling exactly as it was.
   */
  public function testRulingSurvivesIdenticalReimport(): void {
    [$innovation, $a] = $this->conflictingInnovation('TRL 5', 'TRL 7', TRUE);
    $this->reconciler()->refresh($innovation);
    $this->rule($innovation, ['TRL 5' => ContentModel::AUTHORITATIVE, 'TRL 7' => ContentModel::SUPPLEMENTARY]);

    $property = $this->reload($a)->get(ContentModel::CLAIMS)->entity->get(ContentModel::CLAIM_PROPERTY)->entity;
    $this->reimport($a, $this->one, [$this->claim($this->one, $property, 'TRL 5')]);
    $result = $this->reconciler()->refresh($this->reload($innovation));

    $this->assertFalse($result->changed());
    $resolution = $this->resolutions($innovation)[self::READINESS];
    $this->assertSame(ContentModel::STATUS_RESOLVED, $resolution->get(ContentModel::RESOLUTION_STATUS)->value);
    $this->assertSame([
      'TRL 5' => [ContentModel::AUTHORITATIVE, TRUE],
      'TRL 7' => [ContentModel::SUPPLEMENTARY, TRUE],
    ], $this->verdicts($resolution));
  }

  /**
   * Changed claims stale a ruling; the ruling itself is kept.
   */
  public function testChangedClaimsStaleTheRulingWithoutLosingIt(): void {
    [$innovation, , $b] = $this->conflictingInnovation('TRL 5', 'TRL 7', TRUE);
    $this->reconciler()->refresh($innovation);
    $this->rule($innovation, ['TRL 5' => ContentModel::AUTHORITATIVE, 'TRL 7' => ContentModel::REJECTED]);

    $property = $this->reload($b)->get(ContentModel::CLAIMS)->entity->get(ContentModel::CLAIM_PROPERTY)->entity;
    $this->reimport($b, $this->two, [$this->claim($this->two, $property, 'TRL 8')]);
    $result = $this->reconciler()->refresh($this->reload($innovation));

    $this->assertSame(1, $result->staled);
    $resolution = $this->resolutions($innovation)[self::READINESS];
    $this->assertSame(ContentModel::STATUS_STALE, $resolution->get(ContentModel::RESOLUTION_STATUS)->value);
    $this->assertSame([
      'TRL 5' => [ContentModel::AUTHORITATIVE, TRUE],
      'TRL 7' => [ContentModel::REJECTED, FALSE],
      'TRL 8' => [ContentModel::UNREVIEWED, TRUE],
    ], $this->verdicts($resolution));
  }

  /**
   * An untouched pending resolution disappears when sources come to agree.
   */
  public function testUntouchedPendingResolutionFollowsTheClaims(): void {
    [$innovation, , $b] = $this->conflictingInnovation('TRL 5', 'TRL 7', TRUE);
    $this->reconciler()->refresh($innovation);

    $property = $this->reload($b)->get(ContentModel::CLAIMS)->entity->get(ContentModel::CLAIM_PROPERTY)->entity;
    $this->reimport($b, $this->two, [$this->claim($this->two, $property, 'TRL 5')]);
    $result = $this->reconciler()->refresh($this->reload($innovation));

    $this->assertSame(1, $result->removed);
    $this->assertSame([], $this->resolutions($innovation));
  }

  /**
   * A second refresh with nothing new writes nothing.
   */
  public function testRefreshIsIdempotent(): void {
    $innovation = $this->conflictingInnovation('TRL 5', 'TRL 7');
    $this->reconciler()->refresh($innovation);
    $revision = $this->reload($innovation)->getRevisionId();
    $ids = array_map(static fn($resolution) => $resolution->id(), $this->resolutions($innovation));

    $this->assertFalse($this->reconciler()->refresh($this->reload($innovation))->changed());
    $this->assertSame($revision, $this->reload($innovation)->getRevisionId());
    $this->assertSame($ids, array_map(static fn($resolution) => $resolution->id(), $this->resolutions($innovation)));
  }

  /**
   * Refreshing is bookkeeping; a ruling is an attributed revision.
   */
  public function testOnlyRulingsCreateRevisions(): void {
    $innovation = $this->conflictingInnovation('TRL 5', 'TRL 7');
    $before = $this->reload($innovation)->getRevisionId();
    $this->reconciler()->refresh($innovation);
    $this->assertSame($before, $this->reload($innovation)->getRevisionId());

    $this->rule($innovation, ['TRL 5' => ContentModel::REJECTED, 'TRL 7' => ContentModel::AUTHORITATIVE]);

    $after = $this->reload($innovation);
    $this->assertGreaterThan((int) $before, (int) $after->getRevisionId());
    $this->assertSame((int) $this->container->get('current_user')->id(), (int) $after->getRevisionUserId());
    $resolution = $this->resolutions($innovation)[self::READINESS];
    $this->assertSame((int) $this->container->get('current_user')->id(), (int) $resolution->get(ContentModel::RESOLVED_BY)->target_id);
  }

  /**
   * A ruling needs exactly one authoritative value.
   */
  public function testRulingNeedsExactlyOneAuthoritative(): void {
    $innovation = $this->conflictingInnovation('TRL 5', 'TRL 7');
    $this->reconciler()->refresh($innovation);

    foreach ([
      [ContentModel::AUTHORITATIVE, ContentModel::AUTHORITATIVE],
      [ContentModel::SUPPLEMENTARY, ContentModel::REJECTED],
      [ContentModel::AUTHORITATIVE, ContentModel::UNREVIEWED],
    ] as [$first, $second]) {
      try {
        $this->rule($innovation, ['TRL 5' => $first, 'TRL 7' => $second]);
        $this->fail("A ruling of $first / $second must be refused.");
      }
      catch (\InvalidArgumentException) {
        $this->addToAssertionCount(1);
      }
    }
    $this->assertSame(ContentModel::STATUS_PENDING, $this->resolutions($innovation)[self::READINESS]->get(ContentModel::RESOLUTION_STATUS)->value);
  }

  /**
   * A ruling prepared before the claims changed is refused.
   */
  public function testRulingOnChangedClaimsIsRefused(): void {
    [$innovation, , $b] = $this->conflictingInnovation('TRL 5', 'TRL 7', TRUE);
    $this->reconciler()->refresh($innovation);
    $resolution = $this->resolutions($innovation)[self::READINESS];
    $keys = array_keys($this->writer()->currentVerdicts($resolution));

    $property = $this->reload($b)->get(ContentModel::CLAIMS)->entity->get(ContentModel::CLAIM_PROPERTY)->entity;
    $this->reimport($b, $this->two, [$this->claim($this->two, $property, 'TRL 8')]);
    $this->reconciler()->refresh($this->reload($innovation));

    $this->expectException(StaleResolutionException::class);
    $this->writer()->resolve(
      $this->reload($innovation),
      (int) $resolution->id(),
      [$keys[0] => ContentModel::AUTHORITATIVE, $keys[1] => ContentModel::REJECTED],
      '',
      $this->container->get('current_user'),
    );
  }

  /**
   * Builds an innovation whose two sources disagree on readiness.
   *
   * @return \Drupal\node\NodeInterface|array
   *   The innovation, or [innovation, solution A, solution B] when $all.
   */
  private function conflictingInnovation(string $valueA, string $valueB, bool $all = FALSE): NodeInterface|array {
    $a = $this->solution('A', [$this->claim($this->one, $this->property('readiness', self::READINESS), $valueA)]);
    $b = $this->solution('B', [$this->claim($this->two, $this->property('maturity', self::READINESS), $valueB)]);
    $innovation = $this->innovation('Solar pump', [$a, $b]);
    return $all ? [$innovation, $a, $b] : $innovation;
  }

  /**
   * Rules on the readiness resolution, by value label.
   *
   * @param \Drupal\node\NodeInterface $innovation
   *   The innovation.
   * @param array<string, string> $byLabel
   *   Verdict per value label.
   */
  private function rule(NodeInterface $innovation, array $byLabel): void {
    $innovation = $this->reload($innovation);
    $resolution = NULL;
    foreach ($innovation->get(ContentModel::RESOLUTIONS)->referencedEntities() as $candidate) {
      if ($candidate->get(ContentModel::RESOLUTION_PROPERTY)->value === self::READINESS) {
        $resolution = $candidate;
      }
    }
    $this->assertNotNull($resolution);
    $rulings = [];
    foreach ($this->writer()->currentVerdicts($resolution) as $key => $verdict) {
      $rulings[$key] = $byLabel[(string) $verdict->get(ContentModel::VERDICT_VALUE_LABEL)->value];
    }
    $this->writer()->resolve($innovation, (int) $resolution->id(), $rulings, 'Checked the CGIAR record.', $this->container->get('current_user'));
  }

  /**
   * Returns the reconciler.
   */
  private function reconciler(): ResolutionReconciler {
    return $this->container->get('sti_dedupe.resolution_reconciler');
  }

  /**
   * Returns the writer.
   */
  private function writer(): ResolutionWriter {
    return $this->container->get('sti_dedupe.resolution_writer');
  }

}
