<?php

declare(strict_types=1);

namespace Drupal\sti_owner_review;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;
use Psr\Log\LoggerInterface;

/**
 * Applies a curator's decision on an owner's AI proposal.
 *
 * The proposal is JSON in field_ai_proposal, written by the enrichment script (owner_api.py push):
 *
 * @code
 * {"status": "pending",
 *  "fields": {"webpage": "https://...", "description": "...", "parent": "Food and Agriculture Organization"},
 *  "source_type": "scraped", "evidence_url": "https://...", "model": "gemini-2.5-flash", "date": "2026-11-17"}
 * @endcode
 *
 * Nothing in it reaches the owner's own fields until accepted here. Accepting copies the accepted fields and
 * publishes the owner; rejecting changes nothing but the proposal's status, so it is not proposed again. Either
 * way the proposal stays, with the decision, and a revision log message records it. Accepted fields are also
 * flagged as AI-generated in the owner's quality_check paragraphs (QualityChecks); the record status follows on
 * save (RecordStatus).
 */
final class ProposalApplier {

  public const FIELD = 'field_ai_proposal';
  public const PARENT_FIELD = 'field_parent_organization';

  /**
   * Proposal keys and the owner fields they fill.
   */
  public const TARGETS = ['webpage' => 'webpage', 'description' => 'description', 'parent' => self::PARENT_FIELD];

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly AccountInterface $currentUser,
    private readonly TimeInterface $time,
    private readonly LoggerInterface $logger,
    private readonly QualityChecks $qualityChecks,
  ) {}

  /**
   * The owner's proposal, or NULL if it has none or it is not readable JSON.
   */
  public static function proposal(NodeInterface $owner): ?array {
    if (!$owner->hasField(self::FIELD) || $owner->get(self::FIELD)->isEmpty()) {
      return NULL;
    }
    $proposal = json_decode((string) $owner->get(self::FIELD)->value, TRUE);
    return is_array($proposal) && isset($proposal['fields']) && is_array($proposal['fields']) ? $proposal : NULL;
  }

  public static function isPending(NodeInterface $owner): bool {
    return (self::proposal($owner)['status'] ?? NULL) === 'pending';
  }

  /**
   * Copies the accepted fields and publishes the owner.
   *
   * @param string[] $keys
   *   Accepted proposal keys; the others are rejected.
   * @param string|null $parentId
   *   For an accepted "parent": the existing owner it was resolved to, or NULL with $createParent.
   * @param bool $createParent
   *   Create the proposed parent as a new stub owner (needs_update, unpublished), reviewed on its own later.
   *
   * @throws \InvalidArgumentException
   *   When the parent would make a cycle, or is missing. Nothing is saved.
   */
  public function accept(NodeInterface $owner, array $keys, ?string $parentId = NULL, bool $createParent = FALSE): void {
    $proposal = $this->pending($owner);
    $keys = array_values(array_intersect(array_keys($proposal['fields']), $keys));
    if (!$keys) {
      $this->reject($owner);
      return;
    }
    $storage = $this->entityTypeManager->getStorage('node');
    $values = [];
    foreach ($keys as $key) {
      $field = self::TARGETS[$key] ?? NULL;
      if ($field === NULL || !$owner->hasField($field)) {
        throw new \InvalidArgumentException("Owner {$owner->id()} has no field for proposed '$key'.");
      }
      $proposed = (string) $proposal['fields'][$key];
      $values[$field] = match ($key) {
        'webpage' => ['uri' => $proposed, 'title' => ''],
        'description' => ['value' => $proposed, 'format' => 'plain_text'],
        'parent' => $createParent ? NULL : $parentId,
      };
    }
    if (in_array('parent', $keys, TRUE)) {
      if (!$createParent && ($parentId === NULL || !$storage->load($parentId))) {
        throw new \InvalidArgumentException("No parent owner chosen for owner {$owner->id()}.");
      }
      if (!$createParent && ParentChain::wouldCycle($storage, (string) $owner->id(), $parentId)) {
        throw new \InvalidArgumentException("Owner $parentId cannot be the parent of owner {$owner->id()}: it is the owner itself or one of its descendants.");
      }
      if ($createParent) {
        $parent = $storage->create([
          'type' => $owner->bundle(),
          'title' => (string) $proposal['fields']['parent'],
          'field_state' => 'needs_update',
          'status' => 0,
        ]);
        $parent->save();
        $values[self::PARENT_FIELD] = (string) $parent->id();
      }
    }

    foreach ($values as $field => $value) {
      $owner->set($field, $value);
    }
    $this->qualityChecks->markAccepted($owner, array_keys($values));
    $owner->set('field_state', 'published');
    $owner->setPublished();
    $this->finish($owner, $proposal, 'reviewed', $keys);
    $this->verify($owner, $values);
  }

  /**
   * Rejects the whole proposal; with $delete, also requests the owner's deletion (junk).
   */
  public function reject(NodeInterface $owner, bool $delete = FALSE): void {
    $proposal = $this->pending($owner);
    if ($delete) {
      $owner->set('field_state', 'deletion_request');
    }
    $this->finish($owner, $proposal, 'rejected', []);
  }

  private function pending(NodeInterface $owner): array {
    $proposal = self::proposal($owner);
    if (($proposal['status'] ?? NULL) !== 'pending') {
      throw new \InvalidArgumentException("Owner {$owner->id()} has no pending AI proposal.");
    }
    return $proposal;
  }

  /**
   * Records the decision in the proposal and the revision log, and saves.
   */
  private function finish(NodeInterface $owner, array $proposal, string $status, array $accepted): void {
    $rejected = array_values(array_diff(array_keys($proposal['fields']), $accepted));
    $owner->set(self::FIELD, json_encode([
      'status' => $status,
      'accepted' => $accepted,
      'rejected' => $rejected,
      'reviewed_by' => (int) $this->currentUser->id(),
      'reviewed' => $this->time->getRequestTime(),
    ] + $proposal, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    $owner->setNewRevision(TRUE);
    $owner->setRevisionUserId((int) $this->currentUser->id());
    $owner->setRevisionLogMessage(sprintf('AI proposal %s. Accepted: %s. Rejected: %s. (%s, %s, %s%s)',
      $status, implode(', ', $accepted) ?: 'none', implode(', ', $rejected) ?: 'none',
      $proposal['model'] ?? '?', $proposal['date'] ?? '?', $proposal['source_type'] ?? '?',
      isset($proposal['evidence_url']) ? ', ' . $proposal['evidence_url'] : ''));
    $owner->save();
  }

  /**
   * A presave hook or a field setting could drop what was accepted: the save reports success either way.
   */
  private function verify(NodeInterface $owner, array $values): void {
    $saved = $this->entityTypeManager->getStorage('node')->loadUnchanged($owner->id());
    $wrong = [];
    foreach ($values as $field => $value) {
      $item = $saved->get($field)->first();
      $actual = match ($field) {
        'webpage' => $item?->uri,
        'description' => $item?->value,
        default => $item?->target_id === NULL ? NULL : (string) $item->target_id,
      };
      $expected = is_array($value) ? ($value['uri'] ?? $value['value']) : $value;
      if ($actual !== $expected) {
        $wrong[] = $field;
      }
    }
    if ($saved->get('field_state')->value !== 'published' || !$saved->isPublished()) {
      $wrong[] = 'field_state/status';
    }
    if ($wrong) {
      $this->logger->error('Owner @id: AI proposal accepted, but these did not save as accepted: @fields.',
        ['@id' => $owner->id(), '@fields' => implode(', ', $wrong)]);
      throw new \RuntimeException("Owner {$owner->id()}: accepted values did not save (" . implode(', ', $wrong) . ').');
    }
  }

}
