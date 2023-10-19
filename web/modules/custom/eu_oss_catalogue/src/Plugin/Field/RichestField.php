<?php

declare(strict_types = 1);

namespace Drupal\eu_oss_catalogue\Plugin\Field;

use Drupal\Core\Field\FieldItemList;
use Drupal\Core\TypedData\ComputedItemListTrait;

/**
 * Computed field with the configured field's value in the richest translation.
 */
class RichestField extends FieldItemList {

  use ComputedItemListTrait;

  /**
   * {@inheritdoc}
   */
  protected function computeValue(): void {
    if (!($fieldName = $this->getSourceFieldName())) {
      return;
    }

    $this->list = $this->getEntity()
      ->getTranslation($this->getRichestLanguageCode())
      ->get($fieldName)
      ->list;
  }

  /**
   * Returns the machine name of the source field if it is not empty.
   *
   * @return string|null
   *   The machine name of the source field, or NULL if there is no
   *   deterministic source field ot it is empty.
   */
  protected function getSourceFieldName(): ?string {
    $entity = $this->getEntity();
    $sourceField = $this->definition->getSetting('computed_source');
    return $sourceField && $entity->hasField($sourceField) && !$entity->get($sourceField)->isEmpty()
      ? $sourceField
      : NULL;
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
