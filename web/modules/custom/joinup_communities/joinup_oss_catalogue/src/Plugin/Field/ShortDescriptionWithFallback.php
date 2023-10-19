<?php

declare(strict_types = 1);

namespace Drupal\joinup_oss_catalogue\Plugin\Field;

use Drupal\eu_oss_catalogue\Plugin\Field\RichestField;

/**
 * The OSS Solution short description field, falling back to git description.
 */
class ShortDescriptionWithFallback extends RichestField {

  /**
   * {@inheritdoc}
   */
  protected function getSourceFieldName(): ?string {
    $languageCode = $this->getRichestLanguageCode();
    $entity = $this->getEntity()->getTranslation($languageCode);
    foreach (['oss_short_description', 'oss_git_description'] as $fieldName) {
      if (!$entity->hasField($fieldName) || $entity->get($fieldName)->isEmpty()) {
        continue;
      }
      return $fieldName;
    }

    return NULL;
  }

}
