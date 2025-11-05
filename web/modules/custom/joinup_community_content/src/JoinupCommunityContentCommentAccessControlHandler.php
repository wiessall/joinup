<?php

declare(strict_types=1);

namespace Drupal\joinup_community_content;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityHandlerInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\comment\CommentAccessControlHandler;
use Drupal\joinup_community_content\Entity\CommunityContentInterface;
use Drupal\og\OgAccessInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides an access control handler for community content comment entities.
 */
class JoinupCommunityContentCommentAccessControlHandler extends CommentAccessControlHandler implements EntityHandlerInterface {

  private const string ADMIN_PERMISSION = 'administer comments';

  public function __construct(
    EntityTypeInterface $entity_type,
    protected readonly OgAccessInterface $ogAccess,
  ) {
    parent::__construct($entity_type);
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type): self {
    return new static(
      $entity_type,
      $container->get('og.access')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $comment, $operation, AccountInterface $account): AccessResultInterface {
    $community_content = $comment->getCommentedEntity();
    if (!$community_content instanceof CommunityContentInterface) {
      return parent::checkAccess($comment, $operation, $account);
    }

    // Delegate reply op to parent, which will check view and create access.
    if ($operation === 'reply') {
      return parent::checkAccess($comment, $operation, $account);
    }

    switch ($operation) {
      case 'approve':
        return $this->hasCommentAdminAccess($community_content, $account)
          ->andIf(AccessResult::allowedIf(!$comment->isPublished()))
          ->addCacheableDependency($comment);

      case 'view':
        $access = $comment->isPublished()
          ? $this->hasCommentAccess('access comments', $community_content, $account)
          : $this->hasCommentAdminAccess($community_content, $account);

        return $access->andIf($community_content->access($operation, $account, TRUE))
          ->addCacheableDependency($comment);

      case 'update':
        $access = ($account->id() === $comment->getOwnerId() && $comment->isPublished())
          ? $this->hasCommentAccess('edit own comments', $community_content, $account)
          : $this->hasCommentAdminAccess($community_content, $account);

        return $access->addCacheableDependency($comment);

      case 'delete':
        $access = ($account->id() === $comment->getOwnerId() && $comment->isPublished())
          ? $this->hasCommentAccess('delete own comments', $community_content, $account)
          : $this->hasCommentAdminAccess($community_content, $account);

        return $access->addCacheableDependency($comment);
    }

    // No opinion.
    return AccessResult::neutral()->cachePerPermissions();
  }

  /**
   * Checks whether the account has the given permission, site-wide or in group.
   *
   * This also checks the "administer comments" permission.
   */
  protected function hasCommentAccess(string $permission, CommunityContentInterface $community_content, AccountInterface $account): AccessResultInterface {
    // First check site-wide permissions.
    $access = AccessResult::allowedIfHasPermissions($account, [$permission, self::ADMIN_PERMISSION], 'OR');

    // Then organic groups permissions, if not allowed yet.
    // This condition prevents merging expensive cacheability, such as "user"
    // cache context, if we already have site-wide access.
    if (!$access->isAllowed()) {
      $access = $access
        ->orIf($this->ogAccess->userAccess($community_content->getGroup(), $permission, $account))
        ->orIf($this->ogAccess->userAccess($community_content->getGroup(), self::ADMIN_PERMISSION, $account));
    }

    return $access;
  }

  /**
   * Checks whether the account can administer comments, site-wide or in group.
   */
  protected function hasCommentAdminAccess(CommunityContentInterface $community_content, AccountInterface $account): AccessResultInterface {
    // First check site-wide permissions.
    $access = AccessResult::allowedIfHasPermission($account, self::ADMIN_PERMISSION);
    // Then organic groups permissions, if not allowed yet.
    return !$access->isAllowed() ? $access->orIf($this->ogAccess->userAccess($community_content->getGroup(), self::ADMIN_PERMISSION, $account)) : $access;
  }

}
