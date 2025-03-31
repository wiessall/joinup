<?php

declare(strict_types=1);

namespace Drupal\joinup_group\Entity;

use Drupal\Core\Field\EntityReferenceFieldItemList;

interface ShareableContentInterface {

  /**
   * Returns the "shared in" field item list.
   *
   * @return \Drupal\Core\Field\EntityReferenceFieldItemList
   *   The "shared in" field item list.
   */
  public function getSharedInField(): EntityReferenceFieldItemList;

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

}
