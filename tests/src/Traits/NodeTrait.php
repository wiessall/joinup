<?php

declare(strict_types=1);

namespace Drupal\joinup\Traits;

use Drupal\joinup_collection\JoinupCollectionHelper;
use Drupal\node\Entity\Node;

/**
 * Helper methods when dealing with Nodes.
 */
trait NodeTrait {

  use EntityTrait;

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
   * Returns contact_information entity referenced in Joinup collection.
   *
   * @See \joinup_test_install()
   *
   * @return \Drupal\node\Entity\Node
   *   contact_information entity from field "contact" in Joinup collection
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  protected function getDefaultContact(): Node {
    return \Drupal::service('entity.repository')
      ->loadEntityByUuid('node', JoinupCollectionHelper::JOINUP_CONTACT_UUID);
  }

  /**
   * Returns owner entity referenced in Joinup collection.
   *
   * @See \joinup_test_install()
   *
   * @return \Drupal\node\Entity\Node
   *   owner entity from field "contact" in Joinup collection
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  protected function getDefaultOwner(): Node {
    return \Drupal::service('entity.repository')
      ->loadEntityByUuid('node', JoinupCollectionHelper::JOINUP_OWNER_UUID);
  }

}
