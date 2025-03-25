<?php

declare(strict_types=1);

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
      ->condition('parent', '0', '<>')
      ->execute());
    return $storage->load($tids[array_rand($tids)]);
  }

}
