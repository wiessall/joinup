<?php

declare(strict_types = 1);

namespace Drupal\eu_oss_catalogue\EventSubscriber;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\eu_oss_catalogue\Event\EuOssSolutionPreSaveEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Queues new URI values formatted by Imagecache External to be downloaded.
 */
class OssSolutionImagecacheExternalWarmer implements EventSubscriberInterface {

  /**
   * Fields to check for new values.
   *
   * @const string[]
   */
  const FIELDS = [
    'oss_logo',
  ];

  /**
   * Constructs a new OssSolutionImport instance.
   *
   * @param \Drupal\Core\Queue\QueueFactory $queueFactory
   *   The queue factory service.
   */
  public function __construct(
    protected QueueFactory $queueFactory,
  ) {}

  /**
   * Queues new image URLs to be downloaded by the image cache warmer queue.
   *
   * @param \Drupal\eu_oss_catalogue\Event\EuOssSolutionPreSaveEvent $event
   *   The solution presave event.
   */
  public function onSolutionPresave(EuOssSolutionPreSaveEvent $event): void {
    $queue = $this->queueFactory->get('eu_oss_catalogue_imagecache_warmer');
    $queue->createQueue();

    foreach ($this->getChangedFileValues($event->getSolution()) as $url) {
      if (!imagecache_external_validate_host($url)) {
        continue;
      }
      $queue->createItem(['source' => $url]);
    }
  }

  /**
   * Returns the main values of changed field items in a content entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity to parse.
   *
   * @return string[]
   *   The new main values of the fields in static::FIELDS, across all
   *   translations.
   */
  protected function getChangedFileValues(ContentEntityInterface $entity): array {
    $fileUrisToFetch = [];
    $translatableFields = $entity->getTranslatableFields();
    foreach (static::FIELDS as $fieldName) {
      if (!array_key_exists($fieldName, $translatableFields)) {
        $fileUrisToFetch = array_unique(
          array_merge(
            $fileUrisToFetch,
            $this->getChangedValuesFromField($entity, $fieldName)
          )
        );
        continue;
      }

      foreach ($entity->getTranslationLanguages() as $languageCode) {
        $fileUrisToFetch = array_unique(
          array_merge(
            $fileUrisToFetch,
            $this->getChangedValuesFromField($entity->getTranslation($languageCode), $fieldName)
          )
        );
      }
    }
    return $fileUrisToFetch;
  }

  /**
   * Returns the main values of a changed field items of a field.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The host entity of the field.
   * @param string $fieldName
   *   The name of the field.
   *
   * @return string[]
   *   The new main values of the field.
   */
  protected function getChangedValuesFromField(ContentEntityInterface $entity, string $fieldName): array {
    if (!$entity->hasField($fieldName) || ($field = $entity->get($fieldName))->isEmpty()) {
      return [];
    }

    $original = isset($entity->original) && $entity->original->hasTranslation($entity->language()->getId())
      ? $entity->original->getTranslation($entity->language()->getId())
      : NULL;
    $originalFieldIsEmpty = !isset($original) || $original->get($fieldName)->isEmpty();

    if (
      $originalFieldIsEmpty
      || $field->hasAffectingChanges($originalField = $original->get($fieldName))
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
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      EuOssSolutionPreSaveEvent::class => [['onSolutionPresave']],
    ];
  }

}
