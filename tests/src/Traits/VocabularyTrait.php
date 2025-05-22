<?php

declare(strict_types=1);

namespace Drupal\joinup\Traits;

use Drupal\taxonomy\TermInterface;

/**
 * Reusable code concerning vocabularies.
 */
trait VocabularyTrait {

  /**
   * Returns a random term.
   *
   * @param string $vocabulary
   *   The vocabulary name.
   *
   * @return \Drupal\taxonomy\TermInterface
   *   A random topic term.
   */
  protected function getRandomTerm(string $vocabulary): TermInterface {
    $storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');

    $tids = array_values($storage->getQuery()->accessCheck(FALSE)
      ->condition('vid', $vocabulary)
      ->execute());

    $term = $storage->load($tids[array_rand($tids)]);
    assert($term instanceof TermInterface);
    return $term;
  }

}
