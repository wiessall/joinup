<?php

declare(strict_types=1);

namespace Drupal\joinup\Traits;

use Drupal\Component\Uuid\Php;
use Drupal\taxonomy\TermInterface;

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
   * @param bool $skipTopLevel
   *   Skip top level.
   *
   * @return \Drupal\taxonomy\TermInterface
   *   Random term.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function getRandomTerm(string $vocabulary, bool $skipTopLevel = TRUE): TermInterface {
    $storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');

    $query = $storage->getQuery()->accessCheck(FALSE)
      ->condition('vid', $vocabulary);
    if ($skipTopLevel) {
      $query->condition('parent', '0', '<>');
    }

    $tids = array_values($query->execute());
    return $storage->load($tids[array_rand($tids)]);
  }

}
