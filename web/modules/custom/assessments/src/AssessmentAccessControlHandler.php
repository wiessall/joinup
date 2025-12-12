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
 *
 * phpcs:disable Drupal.Arrays.Array.LongLineDeclaration
 *
 * @see https://www.drupal.org/project/coder/issues/3185082
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
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity for which to check access.
   * @param string $operation
   *   The entity operation.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user for which to check access.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  protected function checkAccessForReport(EntityInterface $entity, string $operation, AccountInterface $account): AccessResultInterface {
    // Delegate to operation-specific methods.
    // Only report bundles have a state field.
    $state = $entity->hasField('state') ? ($entity->get('state')->value ?? 'draft') : 'draft';

    return match ($operation) {
      'view' => $this->checkViewAccess($entity, $state, $account),
      'update' => $this->checkUpdateAccess($entity, $state, $account),
      'delete' => $this->checkDeleteAccess($entity, $account),
      'moderate' => $this->checkModerateAccess($entity, $state, $account),
      default => AccessResult::neutral()
        ->cachePerPermissions()
        ->addCacheableDependency($entity),
    };
  }

  /**
   * Performs access checks for public organisation.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity for which to check access.
   * @param string $operation
   *   The entity operation.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user for which to check access.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  protected function checkAccessForPublicOrganisation(EntityInterface $entity, string $operation, AccountInterface $account): AccessResultInterface {
    assert($entity instanceof PublicOrganisation);

    if ($operation !== 'view') {
      return AccessResult::forbidden()
        ->cachePerPermissions()
        ->addCacheableDependency($entity);
    }

    $related_report = $entity->getRelatedReport();

    // Check if the related report exists and is published (status field).
    $is_published = $related_report instanceof AssessmentReport && $related_report->isPublished();

    $result = AccessResult::allowedIfHasPermission($account, 'access content')
      ->allowedIf($is_published)
      ->cachePerPermissions()
      ->addCacheableDependency($entity);

    $related_report !== NULL
      ? $result->addCacheableDependency($related_report)
      : $result->addCacheTags(['assessment_list:report']);

    return $result;
  }

  /**
   * Performs access checks for sub entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity for which to check access.
   * @param string $operation
   *   The entity operation.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user for which to check access.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  protected function checkAccessForSubEntity(EntityInterface $entity, string $operation, AccountInterface $account): AccessResultInterface {
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

    // Published assessments: viewable by everyone with 'access content'
    // permission.
    if ($state === 'published') {
      return AccessResult::allowedIfHasPermission($account, 'access content')
        ->cachePerPermissions()
        ->addCacheableDependency($entity);
    }

    // Non-published assessments (draft, review, require_updates): restricted
    // to owner, assessment reporters and moderators.
    if (in_array($state, ['draft', 'review', 'require_updates'], TRUE)) {
      // Owner can view their own non-published assessment with view permission.
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

    // Other states (if any): deny access by default.
    return AccessResult::neutral()
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
    // Assessment reporters and moderators can update in any state.
    if ($account->hasPermission('bypass assessment report moderation') || $account->hasPermission('moderate assessment report')) {
      return AccessResult::allowed()
        ->cachePerPermissions()
        ->addCacheableDependency($entity);
    }

    // Owners can update only in non-published states with edit permission.
    if (in_array($state, ['draft', 'review', 'require_updates'], TRUE) && $entity->getOwnerId() == $account->id()) {
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
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The assessment entity.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account to check access for.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  protected function checkDeleteAccess(EntityInterface $entity, AccountInterface $account): AccessResultInterface {
    // Assessment reporters and moderators can delete in any state.
    return AccessResult::allowedIfHasPermissions($account, [
      'bypass assessment report moderation',
      'moderate assessment report',
    ], 'OR')
      ->cachePerPermissions()
      ->addCacheableDependency($entity);
  }

  /**
   * Checks moderate access for assessment reports.
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
  protected function checkModerateAccess(EntityInterface $entity, string $state, AccountInterface $account): AccessResultInterface {
    // Only report bundles can be moderated.
    if ($entity->bundle() !== 'report') {
      return AccessResult::forbidden('Only report assessments can be moderated.')
        ->addCacheableDependency($entity);
    }

    // Only reports in review state can be moderated by users with permission.
    return AccessResult::allowedIf($state === 'review')
      ->andIf(AccessResult::allowedIfHasPermission($account, 'moderate assessment report'))
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
      return AccessResult::allowedIfHasPermission($account, 'bypass assessment report moderation');
    }

    // Draft state requires create or moderate permission.
    return AccessResult::allowedIfHasPermissions($account, [
      'create assessment report',
      'moderate assessment report',
    ], 'OR');
  }

}
