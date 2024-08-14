<?php

declare(strict_types=1);

namespace Drupal\joinup_group\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\EntityReferenceFieldItemList;
use Drupal\joinup_core\Entity\PermalinkInterface;

/**
 * Interface for entities that are group content.
 *
 * The following group types can be returned for these bundles:
 * - collections: community content, custom pages, and solutions.
 * - solutions: asset releases and asset distributions.
 */
interface GroupContentInterface extends ContentEntityInterface, PermalinkInterface {

  /**
   * Returns whether or not the entity is associated with a group.
   *
   * @return bool
   *   TRUE if the entity is associated with a group.
   */
  public function hasGroup(): bool;

  /**
   * Returns the group to which this entity belongs to.
   *
   * @return \Drupal\joinup_group\Entity\GroupInterface
   *   The parent group.
   *
   * @throws \Drupal\joinup_group\Exception\MissingGroupException
   *   Thrown when the parent group is missing.
   */
  public function getGroup(): GroupInterface;

  /**
   * Sets the group to which this entity belongs.
   *
   * @param \Drupal\joinup_group\Entity\GroupInterface $group
   *   The parent group ID.
   *
   * @return $this
   *   The called entity.
   */
  public function setGroup(GroupInterface $group): self;

  /**
   * Returns the ID of the group to which this entity belongs.
   *
   * @return string
   *   The parent group ID.
   *
   * @throws \Drupal\joinup_group\Exception\MissingGroupException
   *   Thrown when the parent group is missing.
   */
  public function getGroupId(): string;

  /**
   * Returns the "shared in" field item list.
   *
   * @return \Drupal\Core\Field\EntityReferenceFieldItemList|null
   *   The "shared in" field item list or NULL if it doesn't exist.
   */
  public function getSharedInField(): ?EntityReferenceFieldItemList;

}
