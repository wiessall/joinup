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
   * {@inheritdoc}
   */
  public function getTopicByName(string $name): ?TermInterface {
    $storage = $this->entityTypeManager->getStorage('taxonomy_term');
    $tids = array_values($storage->getQuery()
      ->accessCheck(TRUE)
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
