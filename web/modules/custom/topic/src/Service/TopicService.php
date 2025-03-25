<?php

declare(strict_types=1);

namespace Drupal\topic\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\taxonomy\TermInterface;

/**
 * Topic service.
 */
class TopicService implements TopicServiceInterface {

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Retrieves a taxonomy term by its name from the 'topic' vocabulary.
   *
   * @param string $name
   *   The name of the topic.
   *
   * @return \Drupal\taxonomy\TermInterface|null
   *   The taxonomy term if found, or NULL otherwise.
   */
  public function getTopicByName(string $name): ?TermInterface {
    $storage = $this->entityTypeManager->getStorage('taxonomy_term');
    $tids = array_values($storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('vid', 'topic')
      ->condition('name', $name)
      ->range(0, 1)
      ->execute());

    if (!empty($tids)) {
      return $storage->load(reset($tids));
    }

    return NULL;
  }

}
