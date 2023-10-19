<?php

declare(strict_types = 1);

namespace Drupal\eu_oss_catalogue\Plugin\Field;

use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Entity\TranslatableInterface;
use Drupal\Core\Field\FieldItemList;
use Drupal\Core\TypedData\ComputedItemListTrait;

/**
 * Computed field with the richest entity translation language code.
 */
class RelevantLanguageCode extends FieldItemList {

  use ComputedItemListTrait;

  /**
   * The field name of this field.
   *
   * This constant is used by RichestField instances.
   *
   * @const string
   *
   * @see \Drupal\eu_oss_catalogue\Plugin\Field\RichestField::computeValue()
   */
  const FIELD_NAME = 'relevant_language';

  /**
   * Name of the field which is used to determine the richest translation.
   *
   * @const string
   */
  const FLAGSHIP_FIELD = 'oss_features';

  /**
   * {@inheritdoc}
   */
  protected function computeValue(): void {
    $this->list[0] = $this->createItem(0, $this->getRelevantLanguageCode());
  }

  /**
   * Returns the language code of the richest entity translation.
   *
   * @return string
   *   The language code of the richest entity translation.
   */
  protected function getRelevantLanguageCode(): string {
    $entity = $this->getEntity();

    if (
      !$entity instanceof TranslatableInterface
      || !$entity instanceof FieldableEntityInterface
    ) {
      return $entity->language()->getId();
    }

    $translationLanguages = $entity->getTranslationLanguages(TRUE);
    if (count($translationLanguages) === 1) {
      return key($translationLanguages);
    }

    $stat = array_reduce(
      array_keys($translationLanguages),
      function (array $carry, string $languageCode) use ($entity): array {
        $translation = $entity->getTranslation($languageCode);
        \assert($translation instanceof FieldableEntityInterface);
        $flagShipFieldExists = $translation->hasField(static::FLAGSHIP_FIELD) && !$translation->get(static::FLAGSHIP_FIELD)->isEmpty();

        $carry[$languageCode] = $flagShipFieldExists
          ? (int) $translation->get(static::FLAGSHIP_FIELD)->count()
          : 0;
        return $carry;
      },
      []
    );

    $search = array_search(max($stat), $stat, TRUE);
    \assert($search !== FALSE);
    return $search;
  }

}
