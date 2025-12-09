<?php

declare(strict_types=1);

namespace Drupal\assessments\Entity;

/**
 * Bundle class for assessment sub-entities.
 *
 * This class represents sub-entities such as 'expression', 'participation',
 * and 'public_service' that have a parent report relationship.
 */
class AssessmentSubEntity extends Assessment {

  /**
   * {@inheritdoc}
   */
  public function getRelatedReport(): ?AssessmentReport {
    // Map entity bundles to paragraph field names.
    $field_map = [
      'public_service' => 'affects',
      'expression' => 'expression',
      'participation' => 'has_participation',
    ];

    $field_name = $field_map[$this->bundle()] ?? NULL;
    if (!$field_name) {
      return NULL;
    }

    // Query for paragraphs that reference this entity.
    $paragraph_storage = $this->entityTypeManager()->getStorage('paragraph');
    $paragraph_ids = $paragraph_storage->getQuery()
      ->accessCheck(FALSE)
      ->condition($field_name, $this->id())
      ->range(0, 1)
      ->execute();

    if (empty($paragraph_ids)) {
      return NULL;
    }

    // Load the paragraph and get its parent report.
    $paragraph_id = reset($paragraph_ids);
    $paragraph = $paragraph_storage->load($paragraph_id);

    if (!$paragraph || !$paragraph->hasField('parent_id') || $paragraph->get('parent_id')
        ->isEmpty()) {
      return NULL;
    }

    $parent = $paragraph->get('parent_id')->entity;
    return $parent instanceof AssessmentReport ? $parent : NULL;
  }

}
