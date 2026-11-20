<?php

declare(strict_types=1);

namespace Drupal\sti_owner_review;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;
use Psr\Log\LoggerInterface;

/**
 * The owner's record status (field_record_status, terms of sti_record_status in sti_qa), derived from its data.
 *
 * It is recalculated on every save, so nothing has to remember to set it. The first rule that applies wins:
 *   AI             an AI proposal is pending, and with it every required field would be filled
 *   AI_INCOMPLETE  an AI proposal is pending, but even with it a required field stays empty
 *   REJECTED       the last proposal was rejected and a required field is still empty
 *   INCOMPLETE     a required field is empty (Completeness)
 *   APPROVED       all required fields filled, at least one with an accepted AI value
 *   CURATED        all required fields filled by people (imports count as people's work)
 */
final class RecordStatus {

  public const FIELD = 'field_record_status';
  public const AI = 'AI-enriched';
  public const AI_INCOMPLETE = 'AI-enriched - still incomplete';
  public const REJECTED = 'Rejected - Bad AI-generated content';
  public const INCOMPLETE = 'Incomplete - to be enriched';
  public const APPROVED = 'Approved';
  public const CURATED = 'Manually curated';
  private const VOCABULARY = 'sti_record_status';

  /** @var array<string, int|null> */
  private array $terms = [];

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly QualityChecks $qualityChecks,
    private readonly LoggerInterface $logger,
  ) {}

  public function derive(NodeInterface $owner): string {
    $gaps = Completeness::gaps($owner);
    $proposal = ProposalApplier::proposal($owner);
    $decision = $proposal['status'] ?? NULL;
    if ($decision === 'pending') {
      $covered = array_filter(array_map(fn($key) => ProposalApplier::TARGETS[$key] ?? NULL, array_keys($proposal['fields'])));
      return array_diff($gaps, $covered) ? self::AI_INCOMPLETE : self::AI;
    }
    if ($gaps) {
      return $decision === 'rejected' ? self::REJECTED : self::INCOMPLETE;
    }
    return array_intersect(Completeness::requiredFields($owner), $this->qualityChecks->aiFields($owner)) ? self::APPROVED : self::CURATED;
  }

  /**
   * Sets the derived status; the owner is saved by the caller. A missing term leaves it unchanged, with a warning.
   */
  public function update(NodeInterface $owner): void {
    if ($owner->hasField(self::FIELD) && ($tid = $this->termId($this->derive($owner))) !== NULL) {
      $owner->set(self::FIELD, $tid);
    }
  }

  private function termId(string $name): ?int {
    if (!array_key_exists($name, $this->terms)) {
      $ids = $this->entityTypeManager->getStorage('taxonomy_term')->getQuery()->accessCheck(FALSE)
        ->condition('vid', self::VOCABULARY)->condition('name', $name)->range(0, 1)->execute();
      $this->terms[$name] = $ids ? (int) reset($ids) : NULL;
      if (!$ids) {
        $this->logger->warning('No "@name" term in @vid: record status not set.', ['@name' => $name, '@vid' => self::VOCABULARY]);
      }
    }
    return $this->terms[$name];
  }

}
