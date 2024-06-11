<?php

declare(strict_types=1);

namespace Drupal\eu_oss_catalogue\Plugin\Field;

use Drupal\Core\Field\FieldItemList;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\TypedData\ComputedItemListTrait;
use Drupal\eu_oss_catalogue\Entity\OssSolutionInterface;

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
  const SOURCE_FIELDS_SETTING_KEY = 'eu_oss_catalogue_source_fields';

  /**
   * {@inheritdoc}
   */
  protected function computeValue(): void {
    if ($sourceField = $this->getSourceField()) {
      foreach ($sourceField as $delta => $item) {
        $this->list[$delta] = $item;
      }
    }
  }

  /**
   * Returns the source field if it is not empty.
   *
   * @return \Drupal\Core\Field\FieldItemListInterface|null
   *   The machine name of the source field, or NULL if it is missing on the
   *   entity or the field definition is not properly configured.
   */
  protected function getSourceField(): ?FieldItemListInterface {
    if ($fieldName = $this->getSourceFieldName()) {
      return $this->getEntity()
        ->getTranslation($this->getRichestLanguageCode())
        ->get($fieldName);
    }

    return NULL;
  }

  /**
   * Returns the machine name of the source field if it is not empty.
   *
   * @return string|null
   *   The machine name of the source field, or NULL if it is missing on the
   *   entity or the field definition is not properly configured.
   *
   * @throws \InvalidArgumentException
   *   If an invalid field name is configured.
   */
  protected function getSourceFieldName(): ?string {
    $languageCode = $this->getRichestLanguageCode();
    $ossSolution = $this->getEntity()->getTranslation($languageCode);
    \assert($ossSolution instanceof OssSolutionInterface);
    foreach ((array) $this->definition->getSetting(static::SOURCE_FIELDS_SETTING_KEY) as $fieldName) {
      if ($ossSolution->get($fieldName)->isEmpty()) {
        continue;
      }
      return $fieldName;
    }

    return NULL;
  }

  /**
   * Returns the language code of the richest entity translation.
   *
   * @return string
   *   The language code of the richest entity translation.
   */
  protected function getRichestLanguageCode(): string {
    return $this->getEntity()->getRelevantLangCode();
  }

  /**
   * {@inheritdoc}
   */
  public function postSave($update): bool {
    // Force recompute after save.
    $this->valueComputed = FALSE;
    $this->list = [];
    return parent::postSave($update);
  }

}
