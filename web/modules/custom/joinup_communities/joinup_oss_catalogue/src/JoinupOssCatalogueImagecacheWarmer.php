<?php

declare(strict_types=1);

namespace Drupal\joinup_oss_catalogue;

use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\eu_oss_catalogue\Entity\OssSolutionInterface;

/**
 * Queues new URI values formatted by Imagecache External to be downloaded.
 *
 * @internal
 */
final readonly class JoinupOssCatalogueImagecacheWarmer {

  /**
   * Fields to check for new values.
   *
   * @const string[]
   */
  const FIELDS = [
    'oss_logo',
    'oss_screenshots',
  ];

  public function __construct(
    protected QueueFactory $queueFactory,
  ) {}

  /**
   * Queues image URLs found in the OSS Solution node to be downloaded.
   *
   * @param \Drupal\eu_oss_catalogue\Entity\OssSolutionInterface $ossSolution
   *   The OSS solution node being just inserted or updated.
   */
  public function queueImageUrls(OssSolutionInterface $ossSolution): void {
    $queue = $this->queueFactory->get('joinup_oss_catalogue_imagecache_warmer');
    $queue->createQueue();

    foreach ($this->getFileValues($ossSolution) as $url) {
      if (!imagecache_external_validate_host($url)) {
        continue;
      }
      $queue->createItem(['source' => $url]);
    }
  }

  /**
   * Returns the main values of changed field items in a content entity.
   *
   * This method only parses the "relevant" entity translation.
   *
   * @param \Drupal\eu_oss_catalogue\Entity\OssSolutionInterface $ossSolution
   *   The OSS solution entity to parse.
   *
   * @return string[]
   *   The new main values of the fields in static::FIELDS, fetched from the
   *   relevant translation.
   */
  protected function getFileValues(OssSolutionInterface $ossSolution): array {
    $fileUrisToFetch = [];
    $relevantTranslation = $ossSolution->getTranslation($ossSolution->getRelevantLangCode());
    foreach (self::FIELDS as $fieldName) {
      $fileUrisToFetch = array_unique(
        array_merge(
          $fileUrisToFetch,
          $this->getMainItemValuesFromField($relevantTranslation->get($fieldName)),
        )
      );
    }
    return $fileUrisToFetch;
  }

  /**
   * Returns the main item values from a field item list.
   *
   * @param \Drupal\Core\Field\FieldItemListInterface $fieldItemList
   *   The field item list to parse.
   *
   * @return string[]
   *   The list of main item values. Non-string values are skipped.
   */
  protected function getMainItemValuesFromField(FieldItemListInterface $fieldItemList): array {
    $values = [];
    foreach ($fieldItemList as $item) {
      assert($item instanceof FieldItemInterface);
      $mainValue = $item->get($item->getDataDefinition()->getMainPropertyName())
        ->getValue();
      if (!is_string($mainValue)) {
        continue;
      }
      $values[] = $mainValue;
    }
    return $values;
  }

}
