<?php

declare(strict_types=1);

namespace Drupal\joinup_discussion\Hook;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Menu\MenuLinkManagerInterface;
use Drupal\joinup_discussion\Entity\DiscussionInterface;
use Drupal\joinup_group\GroupMenuLinkHelperTrait;
use Drupal\joinup_group\Plugin\Menu\GroupMenuLink;
use Drupal\node\NodeInterface;

/**
 * Hooks concerning group menu links.
 */
final readonly class DiscussionGroupMenuLinkHooks {

  use GroupMenuLinkHelperTrait;

  public function __construct(
    protected MenuLinkManagerInterface $menuLinkManager,
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Adds a discussions menu link to a group.
   */
  #[Hook('node_insert')]
  public function addDiscussionGroupMenuLink(NodeInterface $discussion): void {
    if ($discussion instanceof DiscussionInterface && $discussion->hasGroup()) {
      $this->addGroupLink(
        linkType: 'discussion',
        group: $discussion->getGroup(),
        ogMenuType: 'navigation',
        title: 'Discussions',
        routeName: 'view.discussions.overview',
        weight: -8,
      );
    }
  }

  /**
   * Deletes a discussion menu link from the group.
   */
  #[Hook('node_delete')]
  public function deleteDiscussionGroupMenuLink(NodeInterface $discussion): void {
    if ($discussion instanceof DiscussionInterface && $discussion->hasGroup()) {
      if (!$this->groupHasContent($discussion)) {
        $pluginId = GroupMenuLink::buildPluginId('discussion', $discussion->getGroupId(), 'navigation');
        $this->menuLinkManager->removeDefinition($pluginId, FALSE);
      }
    }
  }

}
