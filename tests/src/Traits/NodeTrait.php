<?php

declare(strict_types=1);

namespace Drupal\joinup\Traits;

use Drupal\node\Entity\Node;

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
   * Returns contact_information entity referenced in Joinip collection.
   *
   *  @See \joinup_test_install()
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   *
   * @return \Drupal\node\Entity\Node
   *   contact_information entity from field "contact" in Joinup collection
   */
  protected function getDefaultContact(): Node {
    $nodeStorage = \Drupal::entityTypeManager()->getStorage('node');
    $node = $nodeStorage->loadByProperties([
      'type' => 'contact_information',
      'uuid' => 'a1b2c3d4-e5f6-7890-ab12-cd34ef567890',
    ]);
    return array_shift($node);
  }

  /**
   * Returns owner entity referenced in Joinip collection.
   *
   *  @See \joinup_test_install()
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   *
   * @return \Drupal\node\Entity\Node
   *   owner entity from field "contact" in Joinup collection
   */
  protected function getDefaultOwner(): Node {
    $nodeStorage = \Drupal::entityTypeManager()->getStorage('node');
    $node = $nodeStorage->loadByProperties([
      'type' => 'owner',
      'uuid' => 'a1b2c3d4-e5f6-7890-ab12-cd34ef567891',
    ]);
    return array_shift($node);
  }

}
