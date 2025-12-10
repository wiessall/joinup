<?php

declare(strict_types=1);

namespace Drupal\assessments\Entity;

use Drupal\assessments\Entity\Assessment\Report;

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
  public function getRelatedReport(): ?Report {
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

    $paragraph_id = reset($paragraph_ids);
    /** @var \Drupal\paragraphs\ParagraphInterface|null $paragraph */
    $paragraph = $paragraph_storage->load($paragraph_id);

    if (!$paragraph) {
      return NULL;
    }

    $parent = $paragraph->getParentEntity();
    return $parent instanceof Report ? $parent : NULL;
  }

}
