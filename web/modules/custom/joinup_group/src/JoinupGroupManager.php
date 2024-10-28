<?php

declare(strict_types=1);

namespace Drupal\joinup_group;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\joinup_group\Entity\GroupInterface;
use Drupal\og\MembershipManagerInterface;
use Drupal\og\OgAccessInterface;
use Drupal\og\OgContextInterface;
use Drupal\og\OgMembershipInterface;

/**
 * Helper methods related to the Interoperable Europe Portal groups.
 */
class JoinupGroupManager implements JoinupGroupManagerInterface {

  /**
   * The entity type manager.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The OG membership manager service.
   */
  protected MembershipManagerInterface $membershipManager;

  /**
   * The current user.
   */
  protected AccountInterface $currentUser;

  /**
   * The OG access service.
   */
  protected OgAccessInterface $ogAccess;

  /**
   * The Organic Groups context service.
   */
  protected OgContextInterface $ogContext;

  /**
   * Constructs a JoinupRelationshipManager object.
   *
   * @param \Drupal\og\MembershipManagerInterface $membershipManager
   *   The OG membership manager service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\og\OgContextInterface $og_context
   *   The Organic Groups context service.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   * @param \Drupal\og\OgAccessInterface $og_access
   *   The OG access service.
   */
  public function __construct(MembershipManagerInterface $membershipManager, EntityTypeManagerInterface $entityTypeManager, OgContextInterface $og_context, AccountInterface $current_user, OgAccessInterface $og_access) {
    $this->membershipManager = $membershipManager;
    $this->entityTypeManager = $entityTypeManager;
    $this->ogContext = $og_context;
    $this->currentUser = $current_user;
    $this->ogAccess = $og_access;
  }

  /**
   * {@inheritdoc}
   */
  public function getGroupsWhereSoleOwner(AccountInterface $user): array {
    $groups = [];
    foreach (['collection', 'solution'] as $bundle) {
      $memberships = $this->getUserMembershipsByRole($user, "rdf_entity-{$bundle}-administrator");

      // Prepare a list of groups where the user is the sole owner.
      foreach ($memberships as $membership) {
        /** @var \Drupal\joinup_group\Entity\GroupInterface $group */
        $group = $membership->getGroup();
        if ($group->isSoleGroupOwner((int) $user->id())) {
          $groups[$group->id()] = $group;
        }
      }
    }

    return $groups;
  }

  /**
   * {@inheritdoc}
   */
  public function getUserGroupMembershipsByBundle(AccountInterface $user, string $entity_type_id, string $bundle_id, array $states = [OgMembershipInterface::STATE_ACTIVE]): array {
    $storage = $this->getOgMembershipStorage();
    $query = $storage->getQuery()->accessCheck(FALSE)
      ->condition('uid', $user->id())
      ->condition('entity_type', $entity_type_id)
      ->condition('entity_bundle', $bundle_id)
      ->condition('state', $states, 'IN');
    return $storage->loadMultiple($query->execute());
  }

  /**
   * {@inheritdoc}
   */
  public function getUserMembershipsByRole(AccountInterface $user, string $role, array $states = [OgMembershipInterface::STATE_ACTIVE]): array {
    $storage = $this->getOgMembershipStorage();

    // Fetch all the memberships of the user, filtered by role and state.
    $query = $storage->getQuery()->accessCheck(FALSE);
    $query->condition('uid', $user->id());
    $query->condition('roles', $role);
    $query->condition('state', $states, 'IN');
    $result = $query->execute();

    return $storage->loadMultiple($result);
  }

  /**
   * {@inheritdoc}
   */
  public function access(GroupInterface $group, string $og_permission, ?string $site_permission = NULL, ?AccountInterface $account = NULL): AccessResultInterface {
    $account ??= $this->currentUser;

    // Check the site-wide permission.
    $site_access = AccessResult::allowedIf($site_permission);
    if ($site_access->isAllowed()) {
      $site_access = $site_access->andIf(AccessResult::allowedIfHasPermission($account, $site_permission));
    }

    return $site_access
        // Check the OG permission.
      ->orIf($this->ogAccess->userAccess($group, $og_permission, $account))
      ->addCacheableDependency($group)
      ->addCacheContexts(['og_role']);
  }

  /**
   * {@inheritdoc}
   */
  public function getGroupFromContext(): ?GroupInterface {
    $context = $this->ogContext->getRuntimeContexts(['og']);
    return !empty($context['og']) ? $context['og']->getContextValue() : NULL;
  }

  /**
   * Returns the entity storage for OgMembership entities.
   *
   * @return \Drupal\Core\Entity\EntityStorageInterface
   *   The entity storage.
   */
  protected function getOgMembershipStorage(): EntityStorageInterface {
    // Since entities can be dynamically defined in Drupal the generic entity
    // type manager service can throw exceptions in case entities are not
    // available. However these circumstances do not apply to us since we are
    // requesting the OgMembership entities which are defined in code in the OG
    // module on which we correctly depend. Transform these exceptions to
    // unchecked runtime exceptions so we don't need to document these all the
    // way up the call stack.
    try {
      return $this->entityTypeManager->getStorage('og_membership');
    }
    catch (InvalidPluginDefinitionException $e) {
      throw new \RuntimeException('The OgMembership entity has an invalid plugin definition.', NULL, $e);
    }
    catch (PluginNotFoundException $e) {
      throw new \RuntimeException('The OgMembership entity storage does not exist.', NULL, $e);
    }
  }

}
