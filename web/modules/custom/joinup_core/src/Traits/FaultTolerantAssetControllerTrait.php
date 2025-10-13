<?php

declare(strict_types=1);

namespace Drupal\joinup_core\Traits;

/**
 * Trait to modify asset controllers to return empty file when request is wrong.
 */
trait FaultTolerantAssetControllerTrait {

  /**
   * This is how an "empty" asset group should look.
   *
   * The "items" key and its array value is required by the hashing logic.
   *
   * @see \Drupal\Core\Asset\AssetGroupSetHashTrait;
   */
  private const array EMPTY_GROUP = ['items' => []];

  /**
   * {@inheritdoc}
   */
  protected function getGroup(array $groups, int $group_delta): array {
    try {
      $group = parent::getGroup($groups, $group_delta);

      return $group['type'] !== 'external'
        ? $group
        : self::EMPTY_GROUP;
    }
    catch (\Exception) {
    }

    return self::EMPTY_GROUP;
  }

}
