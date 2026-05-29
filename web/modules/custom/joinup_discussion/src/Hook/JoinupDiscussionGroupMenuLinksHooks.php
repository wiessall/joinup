<?php

declare(strict_types=1);

namespace Drupal\joinup_discussion\Hook;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Menu\MenuLinkManagerInterface;
use Drupal\joinup_discussion\Entity\DiscussionInterface;
use Drupal\joinup_group\Entity\GroupInterface;
use Drupal\joinup_group\Plugin\Menu\GroupMenuLink;
use Drupal\joinup_group\Plugin\Menu\GroupMenuLinkDeriver;
use Drupal\node\NodeInterface;

/**
 * Hooks concerning group menu links.
 */
readonly class JoinupDiscussionGroupMenuLinksHooks {

  public function __construct(
    protected MenuLinkManagerInterface $menuLinkManager,
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Adds a 'Discussions' group menu link to the group if necessary.
   */
  #[Hook('node_update')]
  public function addGroupDiscussionLink(NodeInterface $discussion): void {
    if (!$discussion instanceof DiscussionInterface) {
      return;
    }

    $pluginId = GroupMenuLink::buildPluginId('discussion-' . $discussion->getGroupId(), 'navigation');
    if (!$this->menuLinkManager->hasDefinition($pluginId)) {
      $ogMenuInstance = $discussion->getGroup()->getGroupOgMenuInstance('navigation');

      $this->menuLinkManager->addDefinition($pluginId, [
        'class' => GroupMenuLink::class,
        'deriver' => GroupMenuLinkDeriver::class,
        'provider' => 'joinup_group',
        'menu_name' => "ogmenu-{$ogMenuInstance->id()}",
        'title' => 'Discussions',
        'route_name' => 'view.discussions.overview',
        'route_parameters' => ['node' => $discussion->getGroupId()],
        'weight' => -8,
      ]);
    }
  }

  /**
   * Removes the 'Discussions' group menu link with the last discussion.
   *
   * Removal happens also when the group itself is deleted.
   */
  #[Hook('node_delete')]
  public function removeGroupDiscussionLink(NodeInterface $node): void {
    $remove = $node instanceof GroupInterface
      || (
        $node instanceof DiscussionInterface
        && !$this->hasDiscussions($node->getGroupId())
      );

    if ($remove) {
      $id = $node instanceof GroupInterface ? $node->id() : $node->getGroupId();
      $pluginId = GroupMenuLink::buildPluginId("discussion-$id", 'navigation');
      if ($this->menuLinkManager->hasDefinition($pluginId)) {
        $this->menuLinkManager->removeDefinition($pluginId, FALSE);
      }
    }
  }

  /**
   * Checks if the given group has discussions.
   *
   * @param int|string $groupId
   *   The group ID.
   *
   * @return bool
   *   TRUE if the group has discussions, FALSE otherwise.
   */
  protected function hasDiscussions(int|string $groupId): bool {
    return (bool) $this->entityTypeManager->getStorage('node')->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'discussion')
      ->condition('og_audience', $groupId)
      ->count()
      ->execute();
  }

}
