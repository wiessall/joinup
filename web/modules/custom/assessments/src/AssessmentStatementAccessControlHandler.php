<?php

declare(strict_types=1);

namespace Drupal\assessments;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Defines the access control handler for the assessment_statement entity type.
 */
final class AssessmentStatementAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account): AccessResult {
    // Admin permission grants full access.
    $admin_result = AccessResult::allowedIfHasPermission($account, $this->entityType->getAdminPermission())
      ->addCacheableDependency($entity);

    if (!$admin_result->isNeutral()) {
      return $admin_result;
    }

    // Assessment statements are embedded in assessment reports via inline
    // entity form. They should be accessible to anyone who can access the
    // parent assessment. Since they are always embedded and never accessed
    // directly, we allow view access to all authenticated users.
    return match ($operation) {
      'view' => AccessResult::allowedIfHasPermission($account, 'access content')
        ->addCacheableDependency($entity),
      'update', 'delete' => AccessResult::allowedIfHasPermission($account, 'administer assessment statements')
        ->addCacheableDependency($entity),
      default => AccessResult::neutral()
        ->cachePerPermissions()
        ->addCacheableDependency($entity),
    };
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL): AccessResult {
    // Admin permission allows creating any assessment statement.
    $admin_permission = $this->entityType->getAdminPermission();
    if ($admin_permission && $account->hasPermission($admin_permission)) {
      return AccessResult::allowed()->cachePerPermissions();
    }

    // Allow authenticated users to create statements as they are created
    // inline within assessment reports.
    return AccessResult::allowedIfHasPermission($account, 'access content');
  }

}
