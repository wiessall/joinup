<?php

declare(strict_types = 1);

namespace Drupal\eu_oss_catalogue\Plugin\Field;

/**
 * The configured field's value in the richest translation.
 *
 * First non-empty field value wins.
 */
class RichestFieldWithFallback extends RichestField {

  /**
   * {@inheritdoc}
   */
  protected function getSourceFieldName(): ?string {
    $languageCode = $this->getRichestLanguageCode();
    $entity = $this->getEntity()->getTranslation($languageCode);
    foreach ($this->getFieldNames() as $fieldName) {
      if (!$entity->hasField($fieldName) || $entity->get($fieldName)->isEmpty()) {
        continue;
      }
      return $fieldName;
    }

    return NULL;
  }

  /**
   * Returns the machine name of the source fields.
   *
   * @return string[]
   *   The machine name of the source fields, in the order of the fallback
   *   logic.
   */
  protected function getFieldNames(): array {
    $sourceFields = $this->definition->getSetting('computed_sources');
    return $sourceFields && is_array($sourceFields)
      ? $sourceFields
      : [];
  }

}
