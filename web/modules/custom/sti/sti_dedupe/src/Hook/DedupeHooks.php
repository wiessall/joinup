<?php

declare(strict_types=1);

namespace Drupal\sti_dedupe\Hook;

use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\node\NodeInterface;
use Drupal\sti_dedupe\CandidateStore;
use Drupal\sti_dedupe\ContentModel;
use Drupal\sti_dedupe\SameAsClaims;

/**
 * Keeps candidates in step with content, and shows claimed duplicates.
 */
final class DedupeHooks {

  use StringTranslationTrait;

  /**
   * Pseudo-field on atio_innovation listing records claimed to be the same.
   */
  private const POSSIBLY_SAME = 'sti_dedupe_possibly_same';

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly CandidateStore $candidates,
    private readonly SameAsClaims $sameAs,
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

  /**
   * Implements hook_entity_extra_field_info().
   *
   * Hidden until a curator places it in a display, e.g. the claims view mode.
   */
  #[Hook('entity_extra_field_info')]
  public function entityExtraFieldInfo(): array {
    $info['node'][ContentModel::INNOVATION]['display'][self::POSSIBLY_SAME] = [
      'label' => $this->t('Possibly the same'),
      'description' => $this->t('Source records claimed to be the same innovation as one of its sources, not yet among them.'),
      'weight' => 100,
      'visible' => FALSE,
    ];
    return $info;
  }

  /**
   * Implements hook_ENTITY_TYPE_view() for nodes.
   */
  #[Hook('node_view')]
  public function nodeView(array &$build, NodeInterface $node, EntityViewDisplayInterface $display): void {
    if ($node->bundle() !== ContentModel::INNOVATION || !$display->getComponent(self::POSSIBLY_SAME) || !$node->hasField(ContentModel::DERIVED_FROM)) {
      return;
    }
    $sources = array_map('intval', array_column($node->get(ContentModel::DERIVED_FROM)->getValue(), 'target_id'));
    $items = [];
    foreach ($this->entityTypeManager->getStorage('node')->loadMultiple($this->sameAs->linkedTo($sources)) as $record) {
      $items[] = $record->toLink();
    }
    // Claims change when solutions are saved, which invalidates node_list.
    $build[self::POSSIBLY_SAME] = [
      '#theme' => 'item_list',
      '#title' => $this->t('Possibly the same'),
      '#items' => $items,
      '#empty' => $this->t('No other records are claimed to be the same.'),
      '#cache' => ['tags' => ['node_list']],
    ];
  }

}
