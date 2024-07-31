<?php

declare(strict_types=1);

namespace Drupal\joinup_collection\Entity;

use Drupal\joinup_group\Entity\GroupContentInterface;

/**
 * Interface for entities that are collection content.
 *
 * This comprises community content, custom pages, and solutions.
 */
interface CollectionContentInterface extends GroupContentInterface {

  /**
   * Returns the collection to which this entity belongs.
   *
   * @return \Drupal\joinup_collection\Entity\CollectionInterface
   *   The collection. It could be the parent collection or a collection
   *   ancestor.
   *
   * @throws \Drupal\joinup_collection\Exception\MissingCollectionException
   *   Thrown when the collection has not been set on the entity.
   */
  public function getCollection(): CollectionInterface;

}
