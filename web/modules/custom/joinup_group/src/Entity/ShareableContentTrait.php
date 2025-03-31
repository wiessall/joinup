<?php

declare(strict_types=1);

namespace Drupal\joinup_group\Entity;

use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Field\EntityReferenceFieldItemList;
use Drupal\meta_entity\Entity\MetaEntity;

trait ShareableContentTrait {

  /**
   * {@inheritdoc}
   */
  public function getSharedInField(): EntityReferenceFieldItemList {
    return $this->get('field_shared_in');
  }

  /**
   * {@inheritdoc}
   */
  public function shareWith(GroupInterface $group, ?int $timestamp = NULL): void {
    $sharedInMeta = MetaEntity::loadOrCreate('field_shared_in', $this);
    $sharedInMeta->get('field_shared_in')->appendItem([
      'entity' => $group,
      'timestamp' => $timestamp,
    ]);
    $sharedInMeta->save();
    // Tags of the meta entity target are invalidated, but the share target
    // group's tags are invalidated here.
    // @todo Consider adding bundle class for the shared_in meta entity and
    //   handle this there.
    $this->invalidateCacheAfterShareOperation($group);
  }

  /**
   * {@inheritdoc}
   */
  public function unShareFrom(GroupInterface $group): void {
    $sharedInMeta = MetaEntity::loadOrCreate('field_shared_in', $this);
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
    // Tags of the meta entity target are invalidated, but the share target
    // group's tags are invalidated here.
    // @todo Consider adding bundle class for the shared_in meta entity and
    //   handle this there.
    $this->invalidateCacheAfterShareOperation($group);
  }

  protected function invalidateCacheAfterShareOperation(GroupInterface $group): CacheTagsInvalidatorInterface {
    static $cacheTagsInvalidator;
    if (!isset($cacheTagsInvalidator)) {
      $cacheTagsInvalidator = \Drupal::service('cache_tags.invalidator');
      assert($cacheTagsInvalidator instanceof CacheTagsInvalidatorInterface);
    }

    return $cacheTagsInvalidator;
  }

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
    $laterYear = $year + floor(($monthsToAdd + $month) / 12);
    $daysInLaterMonth = cal_days_in_month(CAL_GREGORIAN, $laterMonth, (int) $laterYear);
    $laterDay = $dayInMonth > $daysInLaterMonth
      ? $daysInLaterMonth
      : $dayInMonth;

    $calc = strtotime("$laterYear-$laterMonth-$laterDay");
    $calcDefault = (new DrupalDateTime("@$timestamp"))->modify("+$monthsToAdd month");

    $calcTimestmap = $calc->getTimestamp();
    $calcDefaultTimestmap = $calcDefault->getTimestamp();

    return $calcTimestmap;
  }

}
