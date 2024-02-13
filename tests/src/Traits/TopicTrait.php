<?php

declare(strict_types = 1);

namespace Drupal\joinup\Traits;

use Drupal\topic\Entity\TopicInterface;

/**
 * Reusable code concerning topics.
 */
trait TopicTrait {

  /**
   * Provides a fake topic when none has been explicitly specified.
   *
   * @param array $values
   *   The entity values, passed by reference.
   */
  protected function setFallbackTopic(array &$values): void {
    if (empty($values['field_topic'])) {
      $values['field_topic'] = $this->getRandomTopic()->id();
    }
  }

  /**
   * Returns a random topic from the 2nd level.
   *
   * @return \Drupal\topic\Entity\TopicInterface
   *   A random topic term.
   */
  protected function getRandomTopic(): TopicInterface {
    $storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');

    $tids = array_values($storage->getQuery()->accessCheck(FALSE)
      ->condition('vid', 'topic')
      ->execute());
    // Because of https://www.drupal.org/project/rdf_entity/issues/3333748, we
    // cannot filter out terms without parent.
    // @see https://www.drupal.org/project/rdf_entity/issues/3333748
    // @todo Replace this filtering with a query condition on the parent
    //   taxonomy term in ISAICP-8338.
    $tids = array_filter($tids, fn(string $tid): bool => str_starts_with($tid, 'http://joinup.eu/ontology/topic#'));
    /** @var \Drupal\topic\Entity\TopicInterface $term */
    $term = $storage->load($tids[array_rand($tids)]);

    return $term;
  }

}
