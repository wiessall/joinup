<?php

declare(strict_types=1);

namespace Drupal\assessments;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\assessments\Entity\AssessmentReport;
use Drupal\assessments\Entity\PublicOrganisation;

/**
 * Defines the access control handler for the assessment entity type.
 */
final class AssessmentAccessControlHandler extends EntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account): AccessResultInterface {
    assert($entity instanceof AssessmentInterface);

    // Admin permission grants full access.
    $admin_result = AccessResult::allowedIfHasPermission($account, $this->entityType->getAdminPermission())
      ->addCacheableDependency($entity);

    if (!$admin_result->isNeutral()) {
      return $admin_result;
    }

    return match ($entity->bundle()) {
      'report' => $this->checkAccessForReport($entity, $operation, $account),
      'public_organisation' => $this->checkAccessForPublicOrganisation($entity, $operation, $account),
      'expression', 'participation', 'public_service' => $this->checkAccessForSubEntity($entity, $operation, $account),

      default => AccessResult::neutral()
        ->cachePerPermissions()
        ->addCacheableDependency($entity),
    };
  }

  /**
   * Performs access checks for report.
   *
   * @param \Drupal\assessments\AssessmentInterface $entity
   *   The assessment entity.
   * @param string $operation
   *   The entity operation.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user for which to check access.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  protected function checkAccessForReport(AssessmentInterface $entity, string $operation, AccountInterface $account): AccessResultInterface {
    return match ($operation) {
      'view' => $this->checkViewAccess($entity, $account),
      'update' => $this->checkUpdateAccess($entity, $account),
      'delete' => $this->checkDeleteAccess($entity, $account),
      default => AccessResult::neutral()
        ->cachePerPermissions()
        ->addCacheableDependency($entity),
    };
  }

  /**
   * Performs access checks for public organisation.
   *
   * @param \Drupal\assessments\AssessmentInterface $entity
   *   The assessment entity.
   * @param string $operation
   *   The entity operation.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user for which to check access.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  protected function checkAccessForPublicOrganisation(AssessmentInterface $entity, string $operation, AccountInterface $account): AccessResultInterface {
    assert($entity instanceof PublicOrganisation);

    if ($operation !== 'view') {
      return AccessResult::forbidden()
        ->cachePerPermissions()
        ->addCacheableDependency($entity);
    }

    // First check user permission.
    $access = AccessResult::allowedIfHasPermission($account, 'access content')->addCacheableDependency($entity);
    if (!$access->isAllowed()) {
      return $access;
    }

    // Ensure a related report exists and is accessible.
    $related_report = $entity->getRelatedReport();
    $access->addCacheTags(['assessment_list:report']);
    if (!$related_report instanceof AssessmentReport) {
      return AccessResult::neutral()->addCacheableDependency($access);
    }

    return $access->andIf($related_report->access($operation, $account, TRUE));
  }

  /**
   * Performs access checks for sub entity.
   *
   * @param \Drupal\assessments\AssessmentInterface $entity
   *   The entity for which to check access.
   * @param string $operation
   *   The entity operation.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user for which to check access.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  protected function checkAccessForSubEntity(AssessmentInterface $entity, string $operation, AccountInterface $account): AccessResultInterface {
    $related_report = $entity->getRelatedReport();

    return $related_report instanceof AssessmentReport
      ? $this->checkAccess($related_report, $operation, $account)
        ->addCacheableDependency($entity)
      : AccessResult::forbidden()
        ->cachePerPermissions()
        ->addCacheableDependency($entity);
  }

  /**
   * Checks view access for assessment reports.
   *
   * @param \Drupal\assessments\AssessmentInterface $entity
   *   The assessment entity.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account to check access for.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  protected function checkViewAccess(AssessmentInterface $entity, AccountInterface $account): AccessResultInterface {
    // Published assessments: viewable by everyone with 'access content'
    // permission.
    if ($entity->isPublished()) {
      return AccessResult::allowedIfHasPermission($account, 'access content')
        ->addCacheableDependency($entity);
    }

    // Non-published assessments (draft, review, require_updates): restricted
    // to owner, assessment reporters and moderators.
    // Owner can view their own non-published assessment with view permission.
    $is_owner = $entity->getOwnerId() == $account->id();
    if ($is_owner && $account->hasPermission('view own unpublished assessment report')) {
      return AccessResult::allowed()
        ->cachePerPermissions()
        ->cachePerUser()
        ->addCacheableDependency($entity);
    }

    // Assessment reporters and moderators can view any non-published
    // assessment.
    if ($account->hasPermission('bypass assessment report moderation') || $account->hasPermission('moderate assessment report')) {
      return AccessResult::allowed()
        ->cachePerPermissions()
        ->addCacheableDependency($entity);
    }

    // Deny access with forbidden to show access denied page.
    return AccessResult::forbidden('Non-published assessments are only viewable by the owner, assessment reporters, and moderators.')
      ->cachePerPermissions()
      ->cachePerUser()
      ->addCacheableDependency($entity);
  }

  /**
   * Checks update access for assessment reports.
   *
   * @param \Drupal\assessments\AssessmentInterface $entity
   *   The assessment entity.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account to check access for.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  protected function checkUpdateAccess(AssessmentInterface $entity, AccountInterface $account): AccessResultInterface {
    // Assessment reporters and moderators can update in any state.
    if ($account->hasPermission('bypass assessment report moderation') || $account->hasPermission('moderate assessment report')) {
      return AccessResult::allowed()
        ->cachePerPermissions()
        ->addCacheableDependency($entity);
    }

    // Owners can update only in non-published state with edit permission.
    if (!$entity->isPublished() && $entity->getOwnerId() == $account->id()) {
      return AccessResult::allowedIfHasPermission($account, 'edit own assessment report')
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
   * @param \Drupal\assessments\AssessmentInterface $entity
   *   The assessment entity.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account to check access for.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  protected function checkDeleteAccess(AssessmentInterface $entity, AccountInterface $account): AccessResultInterface {
    // Assessment reporters and moderators can delete in any state.
    return AccessResult::allowedIfHasPermissions($account, [
      'bypass assessment report moderation',
      'moderate assessment report',
    ], 'OR')
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

    // Otherwise check create permission.
    return AccessResult::allowedIfHasPermission($account, 'create assessment report');
  }

}
