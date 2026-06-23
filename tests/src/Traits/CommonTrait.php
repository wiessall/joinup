<?php

declare(strict_types=1);

namespace Drupal\joinup\Traits;

use Drupal\joinup_collection\JoinupCollectionHelper;
use Drupal\node\Entity\Node;

/**
 * Code use by both, Behat and PHPUnit.
 */
trait CommonTrait {

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
