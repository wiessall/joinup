<?php

declare(strict_types=1);

namespace Drupal\joinup_group\Entity;

use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\meta_entity\Entity\MetaEntityInterface;

/**
 * Interface of content items which can be shared between groups.
 */
interface ShareableContentInterface {

  /**
   * List of node bundles which can be shared.
   *
   * @const string[]
   */
  public const array NODE_BUNDLES = [
    'discussion',
    'document',
    'event',
    'news',
    'solution',
  ];

  /**
   * Returns the notification to show on the share form.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup|null
   *   The notification to show, or NULL if no extra notice is needed.
   */
  public function getSharingNotification(): ?TranslatableMarkup;

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
   * @param \Drupal\joinup_group\Entity\GroupInterface[] $groups
   *   The groups to remove the content from.
   */
  public function unShareFrom(array $groups): void;

}
