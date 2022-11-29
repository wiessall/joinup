<?php

declare(strict_types = 1);

namespace Drupal\joinup_group\Cache\Context;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\Context\CacheContextInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\joinup_group\JoinupGroupManagerInterface;

/**
 * Defines a cache context service for group members managers.
 *
 * This cache context should be used in any render elements that have different
 * content when shown to a user that ia able to manage group memberships.
 *
 * Cache context ID: 'group_membership_manager'
 */
class GroupMembershipManagerContext implements CacheContextInterface {

  /**
   * Constructs a new cache context service instance.
   *
   * @param AccountProxyInterface $currentUser
   *   The current user.
   * @param \Drupal\joinup_group\JoinupGroupManagerInterface $groupManager
   *   The group manager service.
   */
  public function __construct(
    protected AccountProxyInterface $currentUser,
    protected JoinupGroupManagerInterface $groupManager,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function getLabel(): MarkupInterface|string {
    return t('Group membership manager');

  }

  /**
   * {@inheritdoc}
   */
  public function getContext(): string {
    if (!$group = $this->groupManager->getGroupFromContext()) {
      throw new \LogicException("The 'group_membership_manager' cache context operates only in the context of a group");
    }
    $access = $this->groupManager->access($group, 'manage members', 'administer organic groups');
    return (string) (int) $access->isAllowed();
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheableMetadata(): CacheableMetadata {
    return (new CacheableMetadata())->setCacheMaxAge(0);
  }

}
