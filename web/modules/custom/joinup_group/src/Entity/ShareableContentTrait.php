<?php

declare(strict_types=1);

namespace Drupal\joinup_group\Entity;

use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\meta_entity\Entity\MetaEntity;
use Drupal\meta_entity\Entity\MetaEntityInterface;

/**
 * Trait of content items which can be shared between groups.
 */
trait ShareableContentTrait {

  /**
   * Returns the meta entity storing the "shares" of the current entity.
   *
   * @return \Drupal\meta_entity\Entity\MetaEntityInterface
   *   Meta entity storing the "shares" of the current entity. Might be a new
   *   entity if this entity wasn't shared ever.
   */
  protected function getSharesMetaEntity(): MetaEntityInterface {
    return MetaEntity::loadOrCreate('field_shared_in', $this);
  }

  /**
   * {@inheritdoc}
   */
  public function getSharedInField(): EntityReferenceFieldItemListInterface {
    $field = $this->getSharesMetaEntity()->get('field_shared_in');
    assert($field instanceof EntityReferenceFieldItemListInterface);
    return $field;
  }

  /**
   * {@inheritdoc}
   */
  public function shareWith(GroupInterface $group, ?int $timestamp = NULL): void {
    $sharedInMeta = $this->getSharesMetaEntity();
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
  public function unShareFrom(GroupInterface $group): void {
    $sharedInMeta = $this->getSharesMetaEntity();
    if ($sharedInMeta->isNew()) {
      return;
    }

    // Find the delta of the item.
    $fieldItemList = $sharedInMeta->get('field_shared_in');
    $rawValue = $fieldItemList->getValue();
    $key = key(
      array_filter(
        $rawValue,
        function (array $itemValue) use ($group): bool {
          return $itemValue['target_id'] == $group->id();
        }
      )
    );
    if (is_null($key)) {
      return;
    }

    $fieldItemList->removeItem($key);
    $sharedInMeta->save();
    $this->invalidateCacheAfterShareOperation($group);
  }

  /**
   * Invalidates the cache.
   *
   * @param \Drupal\joinup_group\Entity\GroupInterface $group
   *   The share target group.
   */
  protected function invalidateCacheAfterShareOperation(GroupInterface $group): void {
    static $cacheTagsInvalidator;
    if (!isset($cacheTagsInvalidator)) {
      $cacheTagsInvalidator = \Drupal::service('cache_tags.invalidator');
      assert($cacheTagsInvalidator instanceof CacheTagsInvalidatorInterface);
    }

    // Tags of the meta entity target are invalidated, but the share target
    // group's tags are invalidated here.
    // @todo Consider adding bundle class for the shared_in meta entity and
    //   handle this there.
    $cacheTagsInvalidator->invalidateTags($group->getCacheTagsToInvalidate());
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
   */
  protected static function someMonthsLater(int $monthsToAdd, ?int $timestamp = NULL): int {
    if ($monthsToAdd < 1) {
      throw new \LogicException(("Number of months must be higher than 0, got '%s'"));
    }

    $timestamp ??= \Drupal::time()->getCurrentTime();
    $dateFormatter = \Drupal::service('date.formatter');
    assert($dateFormatter instanceof DateFormatterInterface);
    $year = (int) $dateFormatter->format($timestamp, 'custom', 'Y');
    $month = (int) $dateFormatter->format($timestamp, 'custom', 'n');
    $dayInMonth = (int) $dateFormatter->format($timestamp, 'custom', 'j');

    $laterMonth = ($monthsToAdd + $month) % 12;
    $laterYear = (int) ($year + floor(($monthsToAdd + $month) / 12));
    $daysInLaterMonth = cal_days_in_month(CAL_GREGORIAN, $laterMonth, $laterYear);
    $laterDay = $dayInMonth > $daysInLaterMonth
      ? $daysInLaterMonth
      : $dayInMonth;

    return strtotime("$laterYear-$laterMonth-$laterDay");
  }

}
