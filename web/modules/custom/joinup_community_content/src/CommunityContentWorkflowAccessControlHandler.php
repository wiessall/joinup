<?php

declare(strict_types=1);

namespace Drupal\joinup_community_content;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\joinup_community_content\Entity\CommunityContentInterface;
use Drupal\joinup_workflow\EntityWorkflowStateInterface;
use Drupal\joinup_workflow\WorkflowHelperInterface;
use Drupal\node\NodeStorageInterface;
use Drupal\og\Entity\OgMembership;
use Drupal\og\MembershipManagerInterface;

/**
 * Access handler for entities with a workflow.
 *
 * @todo Describe the permissions here or in a readme.
 */
class CommunityContentWorkflowAccessControlHandler {

  /**
   * Constructs a new CommunityContentWorkflowAccessControlHandler.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   * @param \Drupal\og\MembershipManagerInterface $membershipManager
   *   The OG membership manager service.
   * @param \Drupal\Core\Session\AccountInterface $currentUser
   *   The current logged in user.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The configuration factory service.
   * @param \Drupal\joinup_workflow\WorkflowHelperInterface $workflowHelper
   *   The workflow helper service.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger
   *   The logger service.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected MembershipManagerInterface $membershipManager,
    protected AccountInterface $currentUser,
    protected ConfigFactoryInterface $configFactory,
    protected WorkflowHelperInterface $workflowHelper,
    protected LoggerChannelFactoryInterface $logger,
  ) {
  }

  /**
   * Main handler for access checks for group content in Joinup.
   *
   * @param \Drupal\joinup_community_content\Entity\CommunityContentInterface $content
   *   The group content entity object.
   * @param string $operation
   *   The CRUD operation.
   * @param \Drupal\Core\Session\AccountInterface|null $account
   *   The user account.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The result of the access check.
   */
  public function entityAccess(CommunityContentInterface $content, $operation, ?AccountInterface $account = NULL): AccessResultInterface {
    $account = $account ?: $this->currentUser;

    // On access is forbidden return the result.
    $access = $this->hasParentViewAccess($content, $account);
    if ($access->isForbidden()) {
      return $access;
    }

    $parent = $content->getGroup();
    // Anything other than view, is denied if the parent is archived.
    if ($operation !== 'view' && $parent->isArchived()) {
      return AccessResult::forbidden();
    }

    switch ($operation) {
      case 'view':
        return $this->entityViewAccess($content, $account);

      case 'update':
        return $this->entityUpdateAccess($content, $account);

      case 'delete':
        return $this->entityDeleteAccess($content, $account);

      case 'post comments':
        $parent_state = $parent instanceof EntityWorkflowStateInterface ? $parent->getWorkflowState() : '';
        $entity_state = $content->getWorkflowState();

        // Commenting on content of an archived group is not allowed.
        if ($parent_state === 'archived' || $entity_state === 'archived') {
          return AccessResult::forbidden();
        }

        $membership = $this->membershipManager->getMembership($parent, $account->id());
        if ($membership instanceof OgMembership) {
          return AccessResult::allowedIf($membership->hasPermission($operation));
        }
    }

    return AccessResult::neutral();
  }

  /**
   * Access check for the 'view' operation.
   *
   * @param \Drupal\joinup_community_content\Entity\CommunityContentInterface $content
   *   The group content entity.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result check.
   */
  protected function entityViewAccess(CommunityContentInterface $content, AccountInterface $account): AccessResultInterface {
    if ($this->hasPublishedVersion($content)) {
      // Published entities are handled by the generic access handler.
      return AccessResult::neutral();
    }

    $isOwner = $account->id() === $content->getOwnerId();
    if ($account->hasPermission('view any unpublished content')
      || ($account->id() === $isOwner && $account->hasPermission('view own unpublished content'))) {
      return AccessResult::allowed();
    }

    if ($group = $content->getGroup()) {
      if ($group->hasGroupPermission($account->id(), 'view any unpublished content')) {
        return AccessResult::allowed();
      }
      elseif ($isOwner && $group->hasGroupPermission($account->id(), 'view own unpublished content')) {
        return AccessResult::allowed();
      }
    }

    return AccessResult::neutral();
  }

  /**
   * Returns whether the user has view permissions to the parent of the entity.
   *
   * @param \Drupal\joinup_community_content\Entity\CommunityContentInterface $content
   *   The group content entity.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user that the permission access is checked.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  protected function hasParentViewAccess(CommunityContentInterface $content, AccountInterface $account): AccessResultInterface {
    $access_handler = $this->entityTypeManager->getAccessControlHandler('rdf_entity');
    return $access_handler->access($content->getGroup(), 'view', $account, TRUE);
  }

  /**
   * Access check for the 'update' operation.
   *
   * @param \Drupal\joinup_community_content\Entity\CommunityContentInterface $content
   *   The group content entity.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result check.
   */
  protected function entityUpdateAccess(CommunityContentInterface $content, AccountInterface $account): AccessResultInterface {
    if ($this->workflowHelper->accountCanNotUnarchive($content, $account)) {
      return AccessResult::forbidden()->addCacheableDependency($content);
    }

    $allowed_states = $this->workflowHelper->getAvailableTargetStates($content, $account);
    if (empty($allowed_states)) {
      return AccessResult::forbidden()->addCacheableDependency($content);
    }

    return AccessResult::neutral();
  }

  /**
   * Access check for 'delete' operation.
   *
   * @param \Drupal\joinup_community_content\Entity\CommunityContentInterface $content
   *   The entity object.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  protected function entityDeleteAccess(CommunityContentInterface $content, AccountInterface $account): AccessResultInterface {
    $anyPermission = "delete any {$content->bundle()} content";

    // Global roles with the 'any permission' can always delete any content.
    if ($account->hasPermission($anyPermission)) {
      return AccessResult::allowed();
    }

    // No users get the `delete own 'bundle' content` in global roles. Access
    // for owned content is checked in OG.
    return AccessResult::neutral();
  }

  /**
   * Checks whether the entity has a published version.
   *
   * @param \Drupal\joinup_community_content\Entity\CommunityContentInterface $content
   *   The entity object.
   *
   * @return bool
   *   Whether the entity has a published version.
   */
  protected function hasPublishedVersion(CommunityContentInterface $content): bool {
    if ($content->isNew()) {
      return FALSE;
    }
    if ($content->isPublished()) {
      return TRUE;
    }
    $published = $this->getNodeStorage()->load($content->id());
    if (!empty($published) && $published instanceof EntityPublishedInterface) {
      return $published->isPublished();
    }
    return FALSE;
  }

  /**
   * Returns the configured permission scheme for the given operation.
   *
   * @param string $operation
   *   The operation for which to return the permission scheme. Can be one of
   *   'create', 'view', 'update', 'delete'.
   *
   * @return array
   *   The permission scheme.
   */
  protected function getPermissionScheme(string $operation): array {
    \assert(\in_array($operation, ['create', 'view', 'update', 'delete']), 'A valid operation should be passed');
    return $this->configFactory->get('joinup_community_content.permission_scheme')->get($operation);
  }

  /**
   * Returns the storage handler for nodes.
   *
   * @return \Drupal\node\NodeStorageInterface
   *   The storage handler.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   *   Thrown if the entity type doesn't exist.
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   *   Thrown when an entity with a non-existing storage is passed.
   */
  protected function getNodeStorage(): NodeStorageInterface {
    /** @var \Drupal\node\NodeStorageInterface $storage */
    $storage = $this->entityTypeManager->getStorage('node');
    return $storage;
  }

}
