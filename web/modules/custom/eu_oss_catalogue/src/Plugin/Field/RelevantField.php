<?php

declare(strict_types = 1);

namespace Drupal\eu_oss_catalogue\Plugin\Field;

use Drupal\Core\Field\FieldItemList;
use Drupal\Core\TypedData\ComputedItemListTrait;

/**
 * Computed field with the configured field's value in the richest translation.
 *
 * This class can also handle fallbacks: if a field is empty or is missing on
 * the entity, it will check whether the next field in the field definition
 * "source_fields" config can be displayed instead.
 *
 * @see \eu_oss_catalogue_entity_bundle_field_info()
 * @see \eu_oss_catalogue_entity_bundle_field_info_alter()
 */
class RelevantField extends FieldItemList {

  use ComputedItemListTrait;

  /**
   * The configuration key storing the name of the source fields.
   *
   * @const string
   */
  const SOURCE_FIELDS_SETTING_KEY = 'source_fields';

  /**
   * {@inheritdoc}
   */
  protected function computeValue(): void {
    if (!($fieldName = $this->getSourceFieldName())) {
      return;
    }

    $richestLangcode = $this->getRichestLanguageCode();
    $sourceField = $this->getEntity()->getTranslation($richestLangcode)->get($fieldName);
    $this->list = $sourceField->isEmpty ? [] : $sourceField->list;
  }

  /**
   * Returns the machine name of the source field if it is not empty.
   *
   * @return string|null
   *   The machine name of the source field, or NULL if it is missing on the
   *   entity or the field definition is not properly configured.
   */
  protected function getSourceFieldName(): ?string {
    $languageCode = $this->getRichestLanguageCode();
    $entity = $this->getEntity()->getTranslation($languageCode);
    foreach ((array) $this->definition->getSetting(static::SOURCE_FIELDS_SETTING_KEY) as $fieldName) {
      if (!$entity->hasField($fieldName) || $entity->get($fieldName)->isEmpty()) {
        continue;
      }
      return $fieldName;
    }

    return NULL;
  }

  /**
   * Returns the language code of the richest entity translation.
   *
   * @return string|null
   *   The language code of the richest entity translation.
   */
  protected function getRichestLanguageCode(): string {
    $entity = $this->getEntity();
    return $entity->hasField(RelevantLanguageCode::FIELD_NAME)
      ? $entity->get(RelevantLanguageCode::FIELD_NAME)->first()->getString()
      : $entity->language()->getId();
  }

}
