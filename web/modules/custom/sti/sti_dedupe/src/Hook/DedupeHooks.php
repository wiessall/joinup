<?php

declare(strict_types=1);

namespace Drupal\sti_dedupe\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\node\NodeInterface;
use Drupal\sti_dedupe\CandidateStore;
use Drupal\sti_dedupe\ContentModel;
use Drupal\sti_dedupe\InnovationLookup;

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
  ) {}

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

}
