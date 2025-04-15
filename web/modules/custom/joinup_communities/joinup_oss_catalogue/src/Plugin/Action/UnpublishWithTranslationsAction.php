<?php

declare(strict_types=1);

namespace Drupal\joinup_oss_catalogue\Plugin\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\Action\Plugin\Action\EntityActionBase;
use Drupal\Core\Session\AccountInterface;
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

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE): AccessResult|bool {
    /** @var \Drupal\Core\Entity\EntityInterface $object */
    $result = $object->access('update', $account, TRUE);

    return $return_as_object ? $result : $result->isAllowed();
  }

}
