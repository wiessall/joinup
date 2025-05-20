<?php

declare(strict_types=1);

namespace Drupal\joinup_oss_catalogue\Plugin\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;

/**
 * Translations action trait.
 */
trait TranslationsActionTrait {

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE): AccessResult|bool {
    $account = $account ?: \Drupal::currentUser();
    $result = AccessResult::allowedIfHasPermission($account, 'administer nodes');
    return $return_as_object ? $result : $result->isAllowed();
  }

}
