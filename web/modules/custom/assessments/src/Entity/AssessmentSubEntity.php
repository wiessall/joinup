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
    $fieldMap = [
      'public_service' => 'affects',
      'expression' => 'expression',
      'participation' => 'has_participation',
    ];

    $fieldName = $fieldMap[$this->bundle()] ?? NULL;
    if (!$fieldName) {
      return NULL;
    }

    // Query for paragraphs that reference this entity.
    $paragraphStorage = $this->entityTypeManager()->getStorage('paragraph');
    $paragraphIds = $paragraphStorage->getQuery()
      ->accessCheck(FALSE)
      ->condition($fieldName, $this->id())
      ->range(0, 1)
      ->execute();

    if (empty($paragraphIds)) {
      return NULL;
    }

    $paragraphId = reset($paragraphIds);
    /** @var \Drupal\paragraphs\ParagraphInterface|null $paragraph */
    $paragraph = $paragraphStorage->load($paragraphId);

    if (!$paragraph) {
      return NULL;
    }

    $parent = $paragraph->getParentEntity();
    return $parent instanceof AssessmentReport ? $parent : NULL;
  }

}
