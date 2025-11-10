<?php

declare(strict_types=1);

namespace Drupal\assessments;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Defines the access control handler for the assessment entity type.
 *
 * phpcs:disable Drupal.Arrays.Array.LongLineDeclaration
 *
 * @see https://www.drupal.org/project/coder/issues/3185082
 */
final class AssessmentAccessControlHandler extends EntityAccessControlHandler {

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

    // Delegate to operation-specific methods.
    $state = $entity->get('state')->value ?? 'draft';

    return match ($operation) {
      'view' => $this->checkViewAccess($entity, $state, $account),
      'update' => $this->checkUpdateAccess($entity, $state, $account),
      'delete' => $this->checkDeleteAccess($entity, $account),
      default => AccessResult::neutral()
        ->cachePerPermissions()
        ->addCacheableDependency($entity),
    };
  }

  /**
   * Checks view access for assessment reports.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The assessment entity.
   * @param string $state
   *   The workflow state of the assessment.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account to check access for.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  protected function checkViewAccess(EntityInterface $entity, string $state, AccountInterface $account): AccessResultInterface {
    $is_owner = $entity->getOwnerId() == $account->id();

    // Published assessments: viewable by anyone with view or moderate
    // permission.
    if ($state === 'published') {
      return AccessResult::allowedIfHasPermissions($account, [
        'view own assessment report',
        'moderate assessment report',
      ], 'OR')
        ->cachePerPermissions()
        ->addCacheableDependency($entity);
    }

    // Draft and review states: owners with view permission OR moderators.
    if ($is_owner && $account->hasPermission('view own assessment report')) {
      return AccessResult::allowed()
        ->cachePerPermissions()
        ->cachePerUser()
        ->addCacheableDependency($entity);
    }

    return AccessResult::allowedIfHasPermission($account, 'moderate assessment report')
      ->cachePerPermissions()
      ->addCacheableDependency($entity);
  }

  /**
   * Checks update access for assessment reports.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The assessment entity.
   * @param string $state
   *   The workflow state of the assessment.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account to check access for.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  protected function checkUpdateAccess(EntityInterface $entity, string $state, AccountInterface $account): AccessResultInterface {
    // Moderators can update in any state.
    if ($account->hasPermission('moderate assessment report')) {
      return AccessResult::allowed()
        ->cachePerPermissions()
        ->addCacheableDependency($entity);
    }

    // Owners can update only in draft state with edit permission.
    if ($state === 'draft' && $entity->getOwnerId() == $account->id()) {
      return AccessResult::allowedIfHasPermission($account, 'edit own assessment report')
        ->cachePerPermissions()
        ->cachePerUser()
        ->addCacheableDependency($entity);
    }

    return AccessResult::neutral()
      ->cachePerPermissions()
      ->cachePerUser()
      ->addCacheableDependency($entity);
  }

  /**
   * Checks delete access for assessment reports.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The assessment entity.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account to check access for.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  protected function checkDeleteAccess(EntityInterface $entity, AccountInterface $account): AccessResultInterface {
    // Only moderators can delete in any state.
    return AccessResult::allowedIfHasPermission($account, 'moderate assessment report')
      ->cachePerPermissions()
      ->addCacheableDependency($entity);
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL): AccessResult {
    // Admin permission allows creating any assessment.
    $admin_permission = $this->entityType->getAdminPermission();
    if ($admin_permission && $account->hasPermission($admin_permission)) {
      return AccessResult::allowed()->cachePerPermissions();
    }

    $target_state = $context['state'] ?? 'draft';

    // Published state requires bypass moderation permission.
    if ($target_state === 'published') {
      return AccessResult::allowedIfHasPermission($account, 'bypass assessment report moderation')
        ->cachePerPermissions();
    }

    // Draft state requires create or moderate permission.
    return AccessResult::allowedIfHasPermissions($account, [
      'create assessment report',
      'moderate assessment report',
    ], 'OR')
      ->cachePerPermissions();
  }

}
