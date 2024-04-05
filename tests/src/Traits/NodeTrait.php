<?php

declare(strict_types=1);

namespace Drupal\joinup\Traits;

/**
 * Helper methods when dealing with Nodes.
 */
trait NodeTrait {

  /**
   * Returns a list of revision IDs.
   *
   * @param string $title
   *   The title of the node.
   * @param string $bundle
   *   The type of the node.
   * @param bool|null $published
   *   Whether to request the last published or last unpublished verion.
   *
   * @return array
   *   A list of revision IDs.
   */
  public function getNodeRevisionIdsList(string $title, string $bundle, ?bool $published = NULL): array {
    $current_revision = $this->getEntityByLabel('node', $title, $bundle);
    // We gather all revisions and then filter out the one we want as filtering
    // by vid will lead in false results.
    // @see: https://www.drupal.org/project/drupal/issues/2766135
    $query = \Drupal::entityQuery('node')
      ->allRevisions()
      ->accessCheck(FALSE)
      ->condition('type', $bundle);
    if ($published !== NULL) {
      $published = (int) $published;
      $query->condition('status', $published);
    }

    $revisions = $query->condition('nid', $current_revision->id())
      ->sort('vid', 'DESC')
      ->execute();

    return empty($revisions) ? [] : array_keys($revisions);
  }

  /**
   * Checks the number of available node entities filtered by bundle.
   *
   * @param int $number
   *   The expected number of node entities.
   * @param string $type
   *   The node type.
   *
   * @throws \Exception
   *   Thrown when the number of node entities does not
   *   match the expectation.
   */
  protected function assertNodeEntityCount(int $number, string $type): void {
    $actual = \Drupal::entityQuery('node')
      ->accessCheck(FALSE)
      ->condition('type', $type)
      ->count()
      ->execute();
    if ($actual != $number) {
      throw new \Exception("Wrong number of $type entities. Expected number: $number, actual number: $actual.");
    }
  }

}
