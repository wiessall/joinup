<?php

declare(strict_types=1);

namespace Drupal\joinup_group\Entity;

use Drupal\Core\Field\EntityReferenceFieldItemListInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\meta_entity\Entity\MetaEntity;

/**
 * Trait of content items which can be shared between groups.
 */
trait ShareableContentTrait {

  /**
   * {@inheritdoc}
   */
  public function getSharingNotification(): ?TranslatableMarkup {
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getSharingMetaEntity(): SharingInterface {
    $metaEntity = MetaEntity::loadOrCreate('shared_in', $this);
    assert($metaEntity instanceof SharingInterface);
    return $metaEntity;
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
  public function shareWith(array $groups, ?int $timestamp = NULL): void {
    $expireTimestamp = $this->getShareExpireTimestamp($timestamp);
    $sharedInMeta = $this->getSharingMetaEntity();
    $sharedInMeta->addSharings($groups, $expireTimestamp);
    $sharedInMeta->save();
  }

  /**
   * Returns the timestamp when the share expires.
   *
   * @param int|null $currentTimestamp
   *   The current timestamp.
   *
   * @return int|null
   *   The share expire timestamp. Can return NULL if the share is permanent.
   */
  protected function getShareExpireTimestamp(?int $currentTimestamp): ?int {
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function unShareFrom(array $groups): void {
    $sharedInMeta = $this->getSharingMetaEntity();
    $sharedInMeta->removeSharings($groups);
    $sharedInMeta->save();
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
    return $dateTime->modify("+$monthsToAdd month")->getTimestamp();
  }

}
