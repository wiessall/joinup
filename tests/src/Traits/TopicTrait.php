<?php

declare(strict_types = 1);

namespace Drupal\joinup\Traits;

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
      $values['field_topic'] = $this->getRandomTopic();
    }
  }

  /**
   * Returns a random topic from the 2nd level.
   *
   * @return string
   *   A random topic term ID.
   */
  protected function getRandomTopic(): string {
    $tids = array_values(\Drupal::entityQuery('taxonomy_term')->accessCheck(FALSE)
      ->condition('vid', 'topic')
      ->execute());
    // Because of https://www.drupal.org/project/rdf_entity/issues/3333748, we
    // cannot filter out terms without parent.
    // @see https://www.drupal.org/project/rdf_entity/issues/3333748
    // @todo Replace this filtering with a query condition on the parent
    //   taxonomy term in ISAICP-8338.
    $tids = array_filter($tids, fn(string $tid): bool => str_starts_with($tid, 'http://joinup.eu/ontology/topic#'));
    return $tids[array_rand($tids)];
  }

}
