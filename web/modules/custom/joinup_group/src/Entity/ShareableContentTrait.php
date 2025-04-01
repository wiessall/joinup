<?php

declare(strict_types=1);

namespace Drupal\joinup_group\Entity;

use Drupal\Core\Cache\Cache;
use Drupal\Core\DestructableInterface;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\meta_entity\Entity\MetaEntity;
use Drupal\meta_entity\Entity\MetaEntityInterface;
use Drupal\search_api\Plugin\search_api\datasource\ContentEntityTrackingManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Trait of content items which can be shared between groups.
 */
trait ShareableContentTrait {

  /**
   * {@inheritdoc}
   */
  public function getSharingMetaEntity(): MetaEntityInterface {
    return MetaEntity::loadOrCreate('shared_in', $this);
  }

  /**
   * {@inheritdoc}
   */
  public function getSharedInField(): EntityReferenceFieldItemListInterface {
    $field = $this->getSharingMetaEntity()->get('field_shared_in');
    assert($field instanceof EntityReferenceFieldItemListInterface);
    return $field;
  }

  /**
   * {@inheritdoc}
   */
  public function shareWith(GroupInterface $group, ?int $timestamp = NULL): void {
    $sharedInMeta = $this->getSharingMetaEntity();
    $sharedInMeta->get('field_shared_in')->appendItem([
      'entity' => $group,
      'timestamp' => $timestamp,
    ]);
    $sharedInMeta->save();
    $this->invalidateCacheAfterShareOperation($group);
  }

  /**
   * {@inheritdoc}
   */
  public function unShareFrom(array $groups): void {
    $sharedInMeta = $this->getSharingMetaEntity();
    if ($sharedInMeta->isNew()) {
      return;
    }

    $groupIds = array_map(
      fn (GroupInterface $group): string|int => $group->id(),
      $groups,
    );

    // Find the delta of the item.
    $fieldItemList = $sharedInMeta->get('field_shared_in');
    $rawValue = $fieldItemList->getValue();
    $keys = array_keys(
      array_filter(
        $rawValue,
        function (array $itemValue) use ($groupIds): bool {
          return in_array($itemValue['target_id'], $groupIds);
        }
      )
    );
    if (empty($keys)) {
      return;
    }

    foreach ($keys as $key) {
      $fieldItemList->removeItem($key);
    }
    $sharedInMeta->save();
    foreach ($groups as $group) {
      $this->invalidateCacheAfterShareOperation($group);
    }
  }

  /**
   * Invalidates the share target's cache and marks it to be re-indexed.
   *
   * @param \Drupal\joinup_group\Entity\GroupInterface $group
   *   The share target group.
   */
  protected function invalidateCacheAfterShareOperation(GroupInterface $group): void {
    // Tags of the meta entity target are invalidated on save, but the share
    // target group's tags are invalidated here.
    Cache::invalidateTags($group->getCacheTagsToInvalidate());

    $contentTrackingManager = \Drupal::getContainer()->get('search_api.entity_datasource.tracking_manager', ContainerInterface::NULL_ON_INVALID_REFERENCE);
    if ($contentTrackingManager instanceof ContentEntityTrackingManager) {
      $contentTrackingManager->trackEntityChange($group);
    }

    $postRequestIndexing = \Drupal::getContainer()->get('search_api.post_request_indexing', ContainerInterface::NULL_ON_INVALID_REFERENCE);
    if ($postRequestIndexing instanceof DestructableInterface) {
      $postRequestIndexing->destruct();
    }
  }

  /**
   * Calculates a later timestamp, specified by number of months.
   *
   * Difference between this and a "natively" modified DateTime object is that
   * the month is incremented as a human would expect it to be incremented. E.g.
   * with this function, if one adds 3 month to 30 november 2024, the result is
   * 28 February 2025, while doing the same with native PHP date operations
   * results in 2 March 2025.
   *
   * @param int $monthsToAdd
   *   Number of months to add. Must be a positive integer.
   * @param int|null $timestamp
   *   The timestamp to modify.
   *
   * @return int
   *   The modified timestamp.
   *
   * @see \Drupal\Tests\joinup_group\Unit\ShareableContentTraitTest::providerTest()
   */
  protected static function someMonthsLater(int $monthsToAdd, ?int $timestamp = NULL): int {
    if ($monthsToAdd < 1) {
      throw new \LogicException(("Number of months must be higher than 0, got '%s'"));
    }

    $timestamp ??= \Drupal::time()->getCurrentTime();
    $dateTime = new \DateTime("@$timestamp");

    $year = (int) $dateTime->format('Y');
    $month = (int) $dateTime->format('n');
    $dayInMonth = (int) $dateTime->format('j');

    $laterMonth = ($monthsToAdd + $month - 1) % 12 + 1;
    $laterYear = (int) ($year + floor(($monthsToAdd + $month - 1) / 12));
    $daysInLaterMonth = cal_days_in_month(CAL_GREGORIAN, $laterMonth, $laterYear);
    $laterDay = $dayInMonth > $daysInLaterMonth ? $daysInLaterMonth : $dayInMonth;

    $laterDateTime = new \DateTime(
      "$laterYear-$laterMonth-$laterDay",
      new \DateTimeZone('UTC')
    );
    return $laterDateTime->getTimestamp();
  }

}
