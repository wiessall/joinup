<?php

declare(strict_types=1);

namespace Drupal\joinup_oss_catalogue\Plugin\Action;

use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\Action\Plugin\Action\EntityActionBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Unpublishes an entity.
 */
#[Action(
  id: 'joinup_oss_catalogue_unpublish_with_translations',
  label: new TranslatableMarkup('Unpublish with all translations'),
  type: 'node'
)]
class UnpublishWithTranslationsAction extends EntityActionBase {

  use TranslationsActionTrait;

  /**
   * {@inheritdoc}
   */
  public function execute($entity = NULL): void {
    $entity->setUnpublished()->save();

    $languages = $entity->getTranslationLanguages(FALSE);
    foreach ($languages as $langcode => $language) {
      $translation = $entity->getTranslation($langcode);

      if ($translation && $translation->isPublished()) {
        $translation->setUnpublished()->save();
      }
    }
  }

}
