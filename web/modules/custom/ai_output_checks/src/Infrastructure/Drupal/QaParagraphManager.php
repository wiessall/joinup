<?php

namespace Drupal\ai_output_checks\Infrastructure\Drupal;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;
use Drupal\paragraphs\Entity\Paragraph;
use Psr\Log\LoggerInterface;

final class QaParagraphManager {

  // Keep these here (Phase 1). Later move to config.
  private string $qaParagraphsField = 'quality_assessment';
  private string $qaParagraphBundle = 'quality_check';
  private string $qaFieldMachineNameField = 'field_content_type_machine_name';
  // STI (Valeria): changed field to check (proposal: split into 2 fields: ai_generated for fields actually AI generated [to be used also todisplay AI-generated fields better; ai_check_allowed for fields on which we want to run the check, even for instance the first time)
  private string $qaAiGeneratedField_flag = 'field_ai_generated';
  private string $qaAiGeneratedField = 'field_ai_check_allowed';
  private string $qaStatusTermField = 'field_quality_assessment';
  private string $qaStatusVocab = 'quality_assessment';

  // Valeria: better to use term iDs? so that we can change the names in tback-end without consequences? IDs: 3360, 3361, 3362, 3363, 3256
  private array $positiveStatuses = [
    'Good',
    'Acceptable',
    'Pre-vetted',
    'Originally good > validated',
    'Originally bad > fixed'
  ];

  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly LoggerInterface $logger,
  ) {}

  private function syncParagraphRevisionOnNode(NodeInterface $node, Paragraph $p): void {
    if (!$node->hasField($this->qaParagraphsField)) {
      return;
    }
    foreach ($node->get($this->qaParagraphsField) as $delta => $item) {
      if ((int) $item->target_id === (int) $p->id()) {
        $node->get($this->qaParagraphsField)->get($delta)->setValue([
          'target_id' => $p->id(),
          'target_revision_id' => $p->getRevisionId(),
        ]);
        $node->save();
        break;
      }
    }
  }

  public function getStatusTermName(NodeInterface $node, string $fieldMachineName): ?string {
    $p = $this->getOrCreate($node, $fieldMachineName);
    if (!$p || !$p->hasField($this->qaStatusTermField)) {
      return NULL;
    }
    $term = $p->get($this->qaStatusTermField)->entity;
    return $term ? (string) $term->label() : NULL;
  }

  public function hasPositiveStatus(NodeInterface $node, string $fieldMachineName): bool {
    $name = $this->getStatusTermName($node, $fieldMachineName);
    // Valeria: the first time the value is null but not positive: in the Action, if this is True, the field is not checked
    // removed the $name !== NULL clause
    return in_array($name, $this->positiveStatuses, TRUE);
  }

  public function setStatusTerm(NodeInterface $node, string $fieldMachineName, string $termName): void {
    $p = $this->getOrCreate($node, $fieldMachineName);
    if (!$p || !$p->hasField($this->qaStatusTermField)) {
      return;
    }

    $storage = $this->entityTypeManager->getStorage('taxonomy_term');
    $terms = $storage->loadByProperties(['vid' => $this->qaStatusVocab, 'name' => $termName]);
    $term = $terms ? reset($terms) : NULL;

    if (!$term) {
      $this->logger->warning(
        'QaParagraphManager: QA term "@name" not found in vocab "@vid".',
        ['@name' => $termName, '@vid' => $this->qaStatusVocab]
      );
      return;
    }

    $p->set($this->qaStatusTermField, ['target_id' => $term->id()]);
    $p->save();
    $this->syncParagraphRevisionOnNode($node, $p);
  }


  /**
   * Returns machine names of fields marked ai_generated=TRUE.
   */
  public function getAiMarkedFields(NodeInterface $node): array {
    if (!$node->hasField($this->qaParagraphsField)) {
      return [];
    }

    $fields = [];

    foreach ($node->get($this->qaParagraphsField) as $item) {
      $p = $item->entity;
      if (!$p instanceof Paragraph) {
        continue;
      }
      if ($p->bundle() !== $this->qaParagraphBundle) {
        continue;
      }
      if (!$p->hasField($this->qaFieldMachineNameField) || !$p->hasField($this->qaAiGeneratedField)) {
        continue;
      }
      if (!(bool) $p->get($this->qaAiGeneratedField)->value) {
        continue;
      }

      $fieldName = trim((string) ($p->get($this->qaFieldMachineNameField)->value ?? ''));
      if ($fieldName !== '') {
        $fields[] = $fieldName;
      }
    }

    return array_values(array_unique($fields));
  }

  /**
   * Ensure QA paragraph exists for $fieldMachineName and return it.
   *
   * Bigger comment:
   * Paragraphs are revisionable. When we save a paragraph, the host node’s
   * entity_reference_revisions item must reference the NEW revision id.
   * So this method saves the node when it creates/attaches a paragraph.
   */
  public function getOrCreate(NodeInterface $node, string $fieldMachineName): ?Paragraph {
    if (!$node->hasField($this->qaParagraphsField)) {
      $this->logger->warning(
        'QaParagraphManager: node @nid has no QA field @field.',
        ['@nid' => $node->id(), '@field' => $this->qaParagraphsField]
      );
      return NULL;
    }

    // Try to find existing paragraph for this field.
    foreach ($node->get($this->qaParagraphsField) as $item) {
      $p = $item->entity;
      if (!$p instanceof Paragraph) {
        continue;
      }
      if ($p->bundle() !== $this->qaParagraphBundle) {
        continue;
      }
      if (!$p->hasField($this->qaFieldMachineNameField)) {
        continue;
      }
      $v = (string) ($p->get($this->qaFieldMachineNameField)->value ?? '');
      if ($v === $fieldMachineName) {
        return $p;
      }
    }

    // Create + attach new paragraph.
    $p = Paragraph::create([
      'type' => $this->qaParagraphBundle,
      $this->qaFieldMachineNameField => $fieldMachineName,
    ]);
    $p->save();

    // Attach as entity_reference_revisions with explicit revision id.
    $node->get($this->qaParagraphsField)->appendItem([
      'target_id' => $p->id(),
      'target_revision_id' => $p->getRevisionId(),
    ]);
    $node->save();

    return $p;
  }

  public function isAiGenerated(NodeInterface $node, string $fieldMachineName): bool {
    $p = $this->getOrCreate($node, $fieldMachineName);
    if (!$p instanceof Paragraph) {
      return FALSE;
    }
    if (!$p->hasField($this->qaAiGeneratedField)) {
      return FALSE;
    }
    return (bool) $p->get($this->qaAiGeneratedField)->value;
  }

  /**
   * Ensure QA row exists and set ai_generated flag.
   * Also updates host node's target_revision_id to the new paragraph revision.
   */
  public function setAiGenerated(NodeInterface $node, string $fieldMachineName, bool $flag): void {
    $p = $this->getOrCreate($node, $fieldMachineName);
    if (!$p instanceof Paragraph || !$p->hasField($this->qaAiGeneratedField)) {
      return;
    }

    $current = (bool) $p->get($this->qaAiGeneratedField)->value;
    if ($current === $flag) {
      return;
    }

    $p->set($this->qaAiGeneratedField, $flag);
    $p->save();

    $this->syncParagraphRevisionOnNode($node, $p);
  }

}
