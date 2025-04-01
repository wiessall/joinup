<?php

declare(strict_types=1);

namespace Drupal\joinup_group\Entity;

use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\meta_entity\Entity\MetaEntityInterface;

/**
 * Interface of content items which can be shared between groups.
 */
interface ShareableContentInterface {

  /**
   * Returns the "shared in" field item list.
   *
   * @return \Drupal\Core\Field\EntityReferenceFieldItemListInterface
   *   The "shared in" field item list.
   */
  public function getSharedInField(): EntityReferenceFieldItemListInterface;

  /**
   * Returns the "shared in" field item list.
   *
   * @return \Drupal\meta_entity\Entity\MetaEntityInterface
   *   The "shared" meta entity.
   */
  public function getSharingMetaEntity(): MetaEntityInterface;

  /**
   * Shares the entity to the given group.
   *
   * @param \Drupal\joinup_group\Entity\GroupInterface $group
   *   The group to share the entity to.
   * @param int|null $timestamp
   *   The timestamp when the sharing expires, or NULL if the sharing is
   *   permanent. Defaults to NULL.
   */
  public function shareWith(GroupInterface $group, ?int $timestamp = NULL): void;

  /**
   * Removes the current node from being shared inside a group.
   *
   * @param \Drupal\joinup_group\Entity\GroupInterface $group
   *   The group where to remove the node.
   */
  public function unShareFrom(GroupInterface $group): void;

}
