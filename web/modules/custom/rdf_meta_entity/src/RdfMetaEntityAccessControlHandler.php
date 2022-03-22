<?php

declare(strict_types = 1);

namespace Drupal\meta_entity;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Provides an access control handler for meta entity.
 */
class RdfMetaEntityAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL): AccessResultInterface {
    $access_result = parent::checkCreateAccess($account, $context, $entity_bundle);
    if ($access_result->isAllowed()) {
      return $access_result;
    }
    return AccessResult::allowedIfHasPermission($account, "create {$entity_bundle} rdf-meta-entity");
  }

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account): AccessResultInterface {
    $access_result = parent::checkAccess($entity, $operation, $account);
    if ($access_result->isAllowed()) {
      return $access_result;
    }

    $permission = "{$operation} {$entity->bundle()} rdf-meta-entity";
    $access_result = AccessResult::allowedIfHasPermission($account, $permission)
      ->addCacheableDependency($entity);
    if (!$access_result->isAllowed()) {
      $access_result->setReason("The '{$permission}' is required");
    }
    return $access_result;
  }

}
