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
final class JoinupOssCatalogueImagecacheWarmer {

  /**
   * Fields to check for new values.
   *
   * @const string[]
   */
  const FIELDS = [
    'oss_logo',
    'oss_screenshots',
  ];

  /**
   * Constructs a new JoinupOssCatalogueImagecacheWarmer instance.
   *
   * @param \Drupal\Core\Queue\QueueFactory $queueFactory
   *   The queue factory service.
   */
  public function __construct(protected QueueFactory $queueFactory) {}

  /**
   * Queues image URLs found in the OSS Solution node to be downloaded.
   *
   * @param \Drupal\eu_oss_catalogue\Entity\OssSolutionInterface $ossSolution
   *   The OSS solution node being just inserted or updated.
   */
  public function queueImageUrls(OssSolutionInterface $ossSolution): void {
    $queue = $this->queueFactory->get('joinup_oss_catalogue_imagecache_warmer');
    $queue->createQueue();

    foreach ($this->getChangedFileValues($ossSolution) as $url) {
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
  protected function getChangedFileValues(OssSolutionInterface $ossSolution): array {
    $fileUrisToFetch = [];
    $relevantTranslation = $ossSolution->getTranslation($ossSolution->getRelevantLangCode());
    foreach (self::FIELDS as $fieldName) {
      $fileUrisToFetch = array_unique(
        array_merge(
          $fileUrisToFetch,
          $this->getChangedValuesFromField($relevantTranslation, $fieldName)
        )
      );
    }
    return $fileUrisToFetch;
  }

  /**
   * Returns the main values of a changed field items of a field.
   *
   * @param \Drupal\eu_oss_catalogue\Entity\OssSolutionInterface $ossSolution
   *   The host entity of the field.
   * @param string $fieldName
   *   The name of the field.
   *
   * @return string[]
   *   The new main values of the field.
   */
  protected function getChangedValuesFromField(OssSolutionInterface $ossSolution, string $fieldName): array {
    if (!$ossSolution->hasField($fieldName) || ($field = $ossSolution->get($fieldName))->isEmpty()) {
      return [];
    }

    $original = $this->getOriginal($ossSolution);
    $originalFieldIsEmpty = !isset($original) || $original->get($fieldName)->isEmpty();
    $originalField = $original?->get($fieldName);

    if (
      $originalFieldIsEmpty ||
      $field->hasAffectingChanges($originalField, $ossSolution->language()->getId())
    ) {
      $originalValues = $originalFieldIsEmpty
        ? []
        : $this->getMainItemValuesFromField($originalField);
      $newValues = $this->getMainItemValuesFromField($field);
      return array_diff($newValues, $originalValues);
    }

    return [];
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
      $mainValue = $item->get($item->getDataDefinition()->getMainPropertyName())->getValue();
      if (!is_string($mainValue)) {
        continue;
      }
      $values[] = $mainValue;
    }
    return $values;
  }

  /**
   * Returns the previous version of the OSS Solution object.
   *
   * @param \Drupal\eu_oss_catalogue\Entity\OssSolutionInterface $ossSolution
   *   The OSS solution.
   *
   * @return \Drupal\eu_oss_catalogue\Entity\OssSolutionInterface|null
   *   The previous translation of the OSS solution object with the same
   *   language code; NULL otherwise.
   */
  protected function getOriginal(OssSolutionInterface $ossSolution): ?OssSolutionInterface {
    if ($ossSolution->original instanceof OssSolutionInterface) {
      $languageCode = $ossSolution->original->getRelevantLangCode();
      return $ossSolution->original->getTranslation($languageCode);
    }
    return NULL;
  }

}
