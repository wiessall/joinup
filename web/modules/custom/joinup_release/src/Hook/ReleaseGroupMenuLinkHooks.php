<?php

declare(strict_types=1);

namespace Drupal\joinup_release\Hook;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Menu\MenuLinkManagerInterface;
use Drupal\joinup_group\GroupMenuLinkHelperTrait;
use Drupal\joinup_release\Entity\ReleaseInterface;
use Drupal\node\NodeInterface;

/**
 * Hook implementations for release group menu links.
 */
final readonly class ReleaseGroupMenuLinkHooks {

  use GroupMenuLinkHelperTrait;

  public function __construct(
    protected MenuLinkManagerInterface $menuLinkManager,
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Adds the release history menu link to a solution.
   */
  #[Hook('node_insert')]
  public function addReleaseHistorySolutionMenuLink(NodeInterface $release): void {
    if ($release instanceof ReleaseInterface && $release->hasGroup()) {
      $this->addGroupLink(
        linkType: 'release',
        group: $release->getGroup(),
        ogMenuType: 'quick_links',
        title: 'Release history',
        routeName: 'joinup_release.solution_release.overview',
      );
    }
  }

  /**
   * Moves the release history menu link to a new group.
   */
  #[Hook('node_update')]
  public function moveReleaseHistorySolutionMenuLink(NodeInterface $release): void {
    if ($release instanceof ReleaseInterface && $release->hasGroup()) {
      $this->moveGroupLink('release', $release, ['quick_links']);
    }
  }

  /**
   * Deletes the discussion menu link from the solution.
   */
  #[Hook('node_delete')]
  public function deleteReleaseHistorySolutionMenuLink(NodeInterface $release): void {
    if ($release instanceof ReleaseInterface && $release->hasGroup()) {
      $this->removeGroupLink('release', $release, ['quick_links']);
    }
  }

}
