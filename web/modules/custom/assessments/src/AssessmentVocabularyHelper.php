<?php

declare(strict_types=1);

namespace Drupal\assessments;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\taxonomy\TermInterface;

/**
 * Helps to interact with assessment vocabularies.
 */
class AssessmentVocabularyHelper {

  public function __construct(
    protected readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Returns a term ID given its string identifier.
   *
   * @param string $vid
   *   The vocabulary.
   * @param string $identifier
   *   The term identifier.
   * @param string $field
   *   The field.
   *
   * @return string
   *   The term ID.
   *
   * @throws \InvalidArgumentException
   *   When no term exists for the passed vocabulary and identifier.
   */
  public function getTermIdFromIdentifier(string $vid, string $identifier, string $field = 'id'): string {
    $tid = $this->entityTypeManager->getStorage('taxonomy_term')->getQuery()
      ->accessCheck(FALSE)
      ->condition('vid', $vid)
      ->condition($field, $identifier)
      ->execute();
    if (!$tid) {
      throw new \InvalidArgumentException("No term exists for vocabulary '$vid' and identifier '$identifier'");
    }

    return reset($tid);
  }

  /**
   * Returns a term entity given its string identifier.
   *
   * @param string $vid
   *   The vocabulary.
   * @param string $identifier
   *   The term identifier.
   *
   * @return \Drupal\taxonomy\TermInterface
   *   The term entity.
   *
   * @throws \InvalidArgumentException
   *   When no term exists for the passed vocabulary and identifier.
   */
  public function getTermFromIdentifier(string $vid, string $identifier): TermInterface {
    return $this->entityTypeManager->getStorage('taxonomy_term')
      ->load($this->getTermIdFromIdentifier($vid, $identifier));
  }

}
