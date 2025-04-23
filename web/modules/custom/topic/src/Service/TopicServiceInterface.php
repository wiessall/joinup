<?php

declare(strict_types=1);

namespace Drupal\topic\Service;

use Drupal\taxonomy\TermInterface;

/**
 * Topic service interface.
 */
interface TopicServiceInterface {

  /**
   * Retrieves a taxonomy term by its name from the 'topic' vocabulary.
   *
   * @param string $name
   *   The name of the topic.
   *
   * @return \Drupal\taxonomy\TermInterface|null
   *   The taxonomy term if found, or NULL otherwise.
   */
  public function getTopicByName(string $name): ?TermInterface;

}
