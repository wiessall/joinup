<?php

declare(strict_types=1);

namespace Drupal\sti_owner_review;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;
use Drupal\paragraphs\ParagraphInterface;
use Psr\Log\LoggerInterface;

/**
 * Per-field QA as in the production portal: one quality_check paragraph per AI-generated field.
 *
 * A field accepted from an AI proposal gets a paragraph with field_ai_generated on and the assessment
 * ACCEPTED. When a curator later changes that field, the flag stays on and the assessment becomes EDITED: the
 * value started as AI output and a person has since worked on it.
 */
final class QualityChecks {

  public const HOST = 'field_quality_checks';
  public const ACCEPTED = 'Good';
  public const EDITED = 'Reviewed';
  private const VOCABULARY = 'quality_assessment';

  /** @var array<string, int|null> */
  private array $terms = [];

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly LoggerInterface $logger,
  ) {}

  /**
   * Flags fields as AI-generated and accepted. The owner is saved by the caller.
   *
   * @param string[] $fields
   *   Owner field machine names.
   */
  public function markAccepted(NodeInterface $owner, array $fields): void {
    if (!$owner->hasField(self::HOST)) {
      return;
    }
    foreach ($fields as $field) {
      $check = $this->check($owner, $field) ?? $this->add($owner, $field);
      $check->set('field_ai_generated', TRUE);
      $check->set('field_quality_assessment', $this->term(self::ACCEPTED));
      $check->setNeedsSave(TRUE);
    }
  }

  /**
   * On a save that changes an AI-generated field, marks it EDITED. Checks touched in this save by
   * markAccepted() are left alone, and so are migrations (syncing saves).
   */
  public function markEdited(NodeInterface $owner): void {
    $original = $owner->isNew() ? NULL : $owner->getOriginal();
    if ($original === NULL || $owner->isSyncing() || !$owner->hasField(self::HOST)) {
      return;
    }
    foreach ($owner->get(self::HOST) as $item) {
      $check = $item->entity;
      if (!$check instanceof ParagraphInterface || $check->isNew() || $check->needsSave() || !$check->get('field_ai_generated')->value) {
        continue;
      }
      $field = (string) $check->get('field_content_type_machine_name')->value;
      if ($owner->hasField($field) && !$owner->get($field)->equals($original->get($field))) {
        $check->set('field_quality_assessment', $this->term(self::EDITED));
        $check->setNeedsSave(TRUE);
      }
    }
  }

  private function check(NodeInterface $owner, string $field): ?ParagraphInterface {
    foreach ($owner->get(self::HOST) as $item) {
      if ($item->entity instanceof ParagraphInterface && $item->entity->get('field_content_type_machine_name')->value === $field) {
        return $item->entity;
      }
    }
    return NULL;
  }

  private function add(NodeInterface $owner, string $field): ParagraphInterface {
    $check = $this->entityTypeManager->getStorage('paragraph')->create([
      'type' => 'quality_check',
      'field_content_type_machine_name' => $field,
    ]);
    $owner->get(self::HOST)->appendItem($check);
    return $check;
  }

  /**
   * The assessment term by name. A missing term leaves the assessment empty, with a warning: the AI flag
   * itself is still recorded.
   */
  private function term(string $name): ?int {
    if (!array_key_exists($name, $this->terms)) {
      $ids = $this->entityTypeManager->getStorage('taxonomy_term')->getQuery()->accessCheck(FALSE)
        ->condition('vid', self::VOCABULARY)->condition('name', $name)->range(0, 1)->execute();
      $this->terms[$name] = $ids ? (int) reset($ids) : NULL;
      if (!$ids) {
        $this->logger->warning('No "@name" term in the @vid vocabulary: quality checks are saved without an assessment.',
          ['@name' => $name, '@vid' => self::VOCABULARY]);
      }
    }
    return $this->terms[$name];
  }

}
