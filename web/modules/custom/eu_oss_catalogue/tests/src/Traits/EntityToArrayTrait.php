<?php

declare(strict_types = 1);

namespace Drupal\Tests\eu_oss_catalogue\Traits;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\EntityReferenceFieldItemListInterface;

/**
 * Trait for transforming entity to array.
 */
trait EntityToArrayTrait {

  /**
   * Converts the given content entity to array.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The content entity to transform to array.
   * @param string[] $excludedProperties
   *   List of entity properties to exclude. Only has effect if
   *   "$includedProperties" is empty.
   * @param string[] $includedProperties
   *   List of entity properties to include.
   *
   * @return array[]
   *   The entity converted to array. If more than one translation is present,
   *   entity values belonging to different translations are keyed by their
   *   language code; Otherwise the only entity array is returned.
   *   Entity reference values are represented by the referenced entity's label.
   *   The array of the default translation is always the first one.
   */
  protected function contentEntityToArray(
    ContentEntityInterface $entity,
    array $excludedProperties = [],
    array $includedProperties = [],
  ): array {
    $entityValues = [
      $entity->language()->getId() => $this->convertEntityTranslationToArray($entity, $excludedProperties, $includedProperties),
    ];

    foreach (array_keys($entity->getTranslationLanguages(FALSE)) as $languageCode) {
      $entityValues[$languageCode] = $this->convertEntityTranslationToArray($entity->getTranslation($languageCode), $excludedProperties, $includedProperties);
    }

    return count($entityValues) === 1
      ? reset($entityValues)
      : $entityValues;
  }

  /**
   * Converts the given content entity translation to an array.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The content entity translation to be transformed.
   * @param string[] $excludedProperties
   *   List of entity properties to exclude. Only has effect if
   *   "$includedProperties" is empty.
   * @param string[] $includedProperties
   *   List of entity properties to include.
   *
   * @return array
   *   The entity translation converted to array. Entity reference values are
   *   represented by the referenced entity's label.
   */
  protected function convertEntityTranslationToArray(
    ContentEntityInterface $entity,
    array $excludedProperties,
    array $includedProperties,
  ): array {
    $entityAsArray = $entity->toArray();
    $valuesArray = $excludedProperties
      ? array_diff_key($entityAsArray, array_combine($excludedProperties, $excludedProperties))
      : array_intersect_key($entityAsArray, array_combine($includedProperties, $includedProperties));
    $translationLangCode = $entity->language()->getId();

    // Gather a list of referenced entities.
    foreach ($entity->getFields(FALSE) as $fieldName => $fieldItemList) {
      if (!array_key_exists($fieldName, $valuesArray)) {
        continue;
      }
      if (!$fieldItemList instanceof EntityReferenceFieldItemListInterface) {
        continue;
      }

      foreach ($fieldItemList->referencedEntities() as $delta => $referencedEntity) {
        if (!$referencedEntity instanceof ContentEntityInterface) {
          break;
        }

        if ($referencedEntity->hasTranslation($translationLangCode)) {
          $referencedEntity = $referencedEntity->getTranslation($translationLangCode);
        }

        $valuesArray[$fieldName][$delta] = $referencedEntity->label() ?: $referencedEntity->id();
      }
    }

    return $valuesArray;
  }

}
