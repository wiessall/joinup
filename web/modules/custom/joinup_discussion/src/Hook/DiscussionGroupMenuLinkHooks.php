<?php

declare(strict_types=1);

namespace Drupal\joinup_discussion\Hook;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Menu\MenuLinkManagerInterface;
use Drupal\joinup_discussion\Entity\DiscussionInterface;
use Drupal\joinup_group\Entity\GroupInterface;
use Drupal\joinup_group\GroupMenuLinkHelperTrait;
use Drupal\joinup_group\Plugin\Menu\GroupMenuLink;
use Drupal\joinup_group\Plugin\Menu\GroupMenuLinkDeriver;
use Drupal\node\NodeInterface;

/**
 * Hooks concerning group menu links.
 */
readonly class DiscussionGroupMenuLinksHooks {

  use GroupMenuLinkHelperTrait;

  public function __construct(
    protected MenuLinkManagerInterface $menuLinkManager,
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Adds a 'Discussions' group menu link to the group if necessary.
   */
  #[Hook('node_update')]
  public function addGroupDiscussionLink(NodeInterface $discussion): void {
    if ($discussion instanceof DiscussionInterface) {
      $this->addGroupLink('discussion', $discussion, 'navigation', 'view.discussions.overview', 'Discussions', -8);
    }
  }

  /**
   * Removes the 'Discussions' group menu link with the last discussion.
   *
   * Removal happens also when the group itself is deleted.
   */
  #[Hook('node_delete')]
  public function removeGroupDiscussionLink(NodeInterface $node): void {
    if ($node instanceof DiscussionInterface || $node instanceof GroupInterface) {
      $this->removeGroupLink('discussion', $node, 'navigation');
    }
  }

}
