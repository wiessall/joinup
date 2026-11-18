<?php

declare(strict_types=1);

namespace Drupal\sti_owner_review;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;
use Psr\Log\LoggerInterface;

/**
 * Why an owner needs review, as production records it: field_record_status, terms of sti_record_status (sti_qa).
 *
 * Workflow state says whether an owner is visible and needs attention (needs_update); the record status says what
 * happened to its content. The review list filters on it to tell AI proposals from other owners needing an update.
 */
final class RecordStatus {

  public const FIELD = 'field_record_status';
  public const AI_ONLY_EMPTY = 'AI-enriched (only empty)';
  public const AI = 'AI-enriched';
  public const APPROVED = 'Approved';
  public const REJECTED = 'Rejected - Bad AI-generated content';
  public const CURATED = 'Manually curated';
  private const VOCABULARY = 'sti_record_status';

  /** @var array<string, int|null> */
  private array $terms = [];

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly LoggerInterface $logger,
  ) {}

  /**
   * Sets the status; the owner is saved by the caller. A missing term leaves it unchanged, with a warning.
   */
  public function set(NodeInterface $owner, string $name): void {
    if (!$owner->hasField(self::FIELD)) {
      return;
    }
    if (!array_key_exists($name, $this->terms)) {
      $ids = $this->entityTypeManager->getStorage('taxonomy_term')->getQuery()->accessCheck(FALSE)
        ->condition('vid', self::VOCABULARY)->condition('name', $name)->range(0, 1)->execute();
      $this->terms[$name] = $ids ? (int) reset($ids) : NULL;
      if (!$ids) {
        $this->logger->warning('No "@name" term in @vid: record status not set.', ['@name' => $name, '@vid' => self::VOCABULARY]);
      }
    }
    if ($this->terms[$name] !== NULL) {
      $owner->set(self::FIELD, $this->terms[$name]);
    }
  }

  /**
   * On a save that makes a proposal pending (owner_api.py push): AI-enriched, "only empty" if every proposed field
   * is still empty on the owner.
   */
  public function onProposal(NodeInterface $owner): void {
    $original = $owner->isNew() ? NULL : $owner->getOriginal();
    if (!ProposalApplier::isPending($owner) || ($original && ProposalApplier::isPending($original))) {
      return;
    }
    foreach (array_keys(ProposalApplier::proposal($owner)['fields']) as $key) {
      $field = ProposalApplier::TARGETS[$key] ?? NULL;
      if ($field !== NULL && $owner->hasField($field) && !$owner->get($field)->isEmpty()) {
        $this->set($owner, self::AI);
        return;
      }
    }
    $this->set($owner, self::AI_ONLY_EMPTY);
  }

}
