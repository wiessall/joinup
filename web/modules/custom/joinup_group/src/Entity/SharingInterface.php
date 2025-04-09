<?php

declare(strict_types=1);

namespace Drupal\joinup_group\Entity;

use Drupal\meta_entity\Entity\MetaEntityInterface;

/**
 * Interface of sharing meta entities.
 */
interface SharingInterface extends MetaEntityInterface {

  /**
   * Shares to the given groups.
   *
   * @param \Drupal\Core\Entity\EntityInterface[] $targets
   *   The entities to share the meta parent to.
   * @param int|null $expiration
   *   The timestamp when the sharing expires. NULL means permanent sharing.
   */
  public function addSharings(array $targets, ?int $expiration): void;

  /**
   * Removes the sharings of the given share target entities.
   *
   * @param \Drupal\Core\Entity\EntityInterface[] $targets
   *   The entities to remove sharing of the meta parent.
   */
  public function removeSharings(array $targets): void;

}
