<?php

declare(strict_types=1);

namespace Drupal\joinup_oss_catalogue\Plugin\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\Action\Plugin\Action\EntityActionBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Publishes an entity and all translations.
 */
#[Action(
  id: 'joinup_oss_catalogue_publish_with_translations',
  label: new TranslatableMarkup('Publish with all translations'),
  type: 'node'
)]
class PublishWithTranslationsAction extends EntityActionBase {

  /**
   * {@inheritdoc}
   */
  public function execute($entity = NULL): void {
    $entity->setPublished()->save();

    $languages = $entity->getTranslationLanguages(FALSE);
    foreach ($languages as $langcode => $language) {
      $translation = $entity->getTranslation($langcode);

      if ($translation && !$translation->isPublished()) {
        $translation->setPublished()->save();
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE): AccessResult|bool {
    $result = AccessResult::allowedIfHasPermissions($account, [
      'administer nodes',
    ]);

    return $return_as_object ? $result : $result->isAllowed();
  }

}
