<?php

declare(strict_types=1);

namespace Drupal\assessments;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityAccessControlHandler;
use Drupal\Core\Entity\EntityHandlerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\assessments\Entity\AssessmentReport;
use Drupal\assessments\Entity\PublicOrganisation;
use Drupal\joinup_workflow\EntityWorkflowStateInterface;
use Drupal\joinup_workflow\WorkflowHelperInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines the access control handler for the assessment entity type.
 */
final class AssessmentAccessControlHandler extends EntityAccessControlHandler implements EntityHandlerInterface {

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type): static {
    return new static(
      $entity_type,
      $container->get('joinup_workflow.workflow_helper'),
    );
  }

  public function __construct(
    EntityTypeInterface $entity_type,
    protected readonly WorkflowHelperInterface $workflowHelper,
  ) {
    parent::__construct($entity_type);
  }

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account): AccessResultInterface {
    assert($entity instanceof AssessmentInterface);

    // Admin permission grants full access.
    $adminResult = AccessResult::allowedIfHasPermission($account, $this->entityType->getAdminPermission())
      ->addCacheableDependency($entity);

    if (!$adminResult->isNeutral()) {
      return $adminResult;
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
    $relatedReport = $entity->getRelatedReport();
    $access->addCacheTags(['assessment_list:report']);
    if (!$relatedReport instanceof AssessmentReport) {
      return AccessResult::neutral()->addCacheableDependency($access);
    }

    return $access->andIf($relatedReport->access($operation, $account, TRUE));
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
    $relatedReport = $entity->getRelatedReport();

    return $relatedReport instanceof AssessmentReport
      ? $this->checkAccess($relatedReport, $operation, $account)
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

    $isOwner = $entity->getOwnerId() == $account->id();
    if ($isOwner && $account->hasPermission('view own unpublished assessment report')) {
      return AccessResult::allowed()
        ->cachePerPermissions()
        ->cachePerUser()
        ->addCacheableDependency($entity);
    }

    if ($account->hasPermission('view any unpublished assessment report')) {
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
    $allowedStates = $this->workflowHelper->getAvailableTargetStates($entity, $account);
    if (!empty($allowedStates)) {
      return AccessResult::allowed()
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
   * @param \Drupal\assessments\AssessmentInterface $entity
   *   The assessment entity.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account to check access for.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  protected function checkDeleteAccess(AssessmentInterface $entity, AccountInterface $account): AccessResultInterface {
    $deleteOwnDraftAccess = AccessResult::allowedIfHasPermission($account, 'delete own draft assessment report')
      ->andIf(AccessResult::allowedIf(
        $entity instanceof EntityWorkflowStateInterface
        && $entity->getWorkflowState() === 'draft'
        && $entity->getOwnerId() == $account->id()
      )
        ->cachePerUser()
        ->addCacheableDependency($entity)
      );

    return AccessResult::allowedIfHasPermission($account, 'delete assessment report')
      ->orIf($deleteOwnDraftAccess)
      ->addCacheableDependency($entity);
  }

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL): AccessResult {
    // Admin permission allows creating any assessment.
    $adminPermission = $this->entityType->getAdminPermission();
    if ($adminPermission && $account->hasPermission($adminPermission)) {
      return AccessResult::allowed()->cachePerPermissions();
    }

    // Otherwise check create permission.
    return AccessResult::allowedIfHasPermission($account, 'create assessment report');
  }

}
