<?php

declare(strict_types=1);

namespace Drupal\sti_dedupe\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\node\NodeInterface;
use Drupal\sti_dedupe\CandidateStore;
use Drupal\sti_dedupe\ConflictQueue;
use Drupal\sti_dedupe\ContentModel;
use Drupal\sti_dedupe\InnovationLookup;
use Drupal\taxonomy\TermInterface;

/**
 * Keeps candidates and claim conflicts in step with content changes.
 *
 * Nothing heavy happens in these hooks: they only work out which
 * innovations are affected and queue them. A migration saving 1400
 * solutions must not refresh conflicts 1400 times inline.
 */
final class DedupeHooks {

  public function __construct(
    private readonly CandidateStore $candidates,
    private readonly InnovationLookup $innovations,
    private readonly ConflictQueue $conflictQueue,
  ) {}

  /**
   * Implements hook_node_insert().
   */
  #[Hook('node_insert')]
  public function nodeInsert(NodeInterface $node): void {
    $this->nodeChanged($node);
  }

  /**
   * Implements hook_node_update().
   */
  #[Hook('node_update')]
  public function nodeUpdate(NodeInterface $node): void {
    $this->nodeChanged($node);
  }

  /**
   * Implements hook_node_delete().
   *
   * Clears candidate rows for a deleted solution. Decisions are kept: they
   * are the audit trail, and a decision naming a deleted node is harmless.
   */
  #[Hook('node_delete')]
  public function nodeDelete(NodeInterface $node): void {
    if ($node->bundle() === ContentModel::SOLUTION) {
      $this->candidates->deleteForNode((int) $node->id());
    }
  }

  /**
   * Implements hook_taxonomy_term_insert().
   */
  #[Hook('taxonomy_term_insert')]
  public function termInsert(TermInterface $term): void {
    $this->termChanged($term, NULL);
  }

  /**
   * Implements hook_taxonomy_term_update().
   */
  #[Hook('taxonomy_term_update')]
  public function termUpdate(TermInterface $term): void {
    $original = $term->getOriginal();
    $this->termChanged($term, $original instanceof TermInterface ? $original : NULL);
  }

  /**
   * Queues the innovations a node change can affect.
   *
   * A solution's claims changed: every innovation derived from it. An
   * innovation's list of sources changed: that innovation.
   */
  private function nodeChanged(NodeInterface $node): void {
    if ($node->bundle() === ContentModel::SOLUTION) {
      $this->conflictQueue->queue($this->innovations->derivedFrom([(int) $node->id()]));
      return;
    }
    if ($node->bundle() !== ContentModel::INNOVATION || !$node->hasField(ContentModel::DERIVED_FROM)) {
      return;
    }
    $original = $node->getOriginal();
    $before = $original instanceof NodeInterface ? $this->sources($original) : [];
    if ($before !== $this->sources($node)) {
      $this->conflictQueue->queue([(int) $node->id()]);
    }
  }

  /**
   * Queues a full refresh when a property or value mapping changed.
   */
  private function termChanged(TermInterface $term, ?TermInterface $original): void {
    $field = match ($term->bundle()) {
      ContentModel::CLAIM_PROPERTIES => ContentModel::ATIO_PROPERTY,
      ContentModel::CLAIM_VALUES => ContentModel::EXACT_MATCH,
      default => NULL,
    };
    if ($field === NULL || !$term->hasField($field)) {
      return;
    }
    $before = $original?->get($field)->value;
    if ($before !== $term->get($field)->value) {
      $this->conflictQueue->queueAll();
    }
  }

  /**
   * Returns the sorted source solution ids of an innovation.
   *
   * @return int[]
   *   Solution node ids.
   */
  private function sources(NodeInterface $innovation): array {
    $ids = array_map(static fn(array $item): int => (int) $item['target_id'], $innovation->get(ContentModel::DERIVED_FROM)->getValue());
    sort($ids);
    return $ids;
  }

}
