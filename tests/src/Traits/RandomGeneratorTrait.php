<?php

declare(strict_types=1);

namespace Drupal\joinup\Traits;

use Drupal\Component\Uuid\Php;
use Drupal\Core\Entity\EntityInterface;

/**
 * Helper methods for generating random data in tests.
 */
trait RandomGeneratorTrait {

  /**
   * Returns a random URI ID for the collection.
   *
   * @return string
   *   A string URI
   */
  public function getRandomUri(): string {
    $php = new Php();
    return 'http://example.com/' . $php->generate();
  }

  /**
   * Returns random taxonomy term from vocabulary.
   *
   * @param string $vocabulary
   *   Vocabulary id.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   Random term.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function getRandomTerm(string $vocabulary): EntityInterface {
    $storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');

    $query = $storage->getQuery()->accessCheck(FALSE)
      ->condition('vid', $vocabulary);
    // Only get last level terms for the EIRA vocabulary.
    if ($vocabulary === 'solution_type') {
      $query->condition('parent', '0', '<>');
    }

    $tids = array_values($query->execute());
    return $storage->load($tids[array_rand($tids)]);
  }

}
