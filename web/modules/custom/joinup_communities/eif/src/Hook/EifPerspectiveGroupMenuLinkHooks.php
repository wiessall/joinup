<?php

declare(strict_types=1);

namespace Drupal\eif\Hook;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Menu\MenuLinkManagerInterface;
use Drupal\eif\Entity\EifPerspectiveInterface;
use Drupal\joinup_group\GroupMenuLinkHelperTrait;
use Drupal\joinup_solution\Entity\SolutionInterface;
use Drupal\meta_entity\Entity\MetaEntityInterface;

/**
 * Hook implementations for EIF perspective solution menu links.
 */
final readonly class EifPerspectiveGroupMenuLinkHooks {

  use GroupMenuLinkHelperTrait;

  public function __construct(
    protected MenuLinkManagerInterface $menuLinkManager,
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Adds a solution menu link when a new EIF perspective is created.
   */
  #[Hook('meta_entity_insert')]
  public function addSolutionMenuLink(MetaEntityInterface $eifPerspective): void {
    if ($eifPerspective instanceof EifPerspectiveInterface) {
      foreach (['navigation', 'quick_links'] as $ogMenuType) {
        $solution = $eifPerspective->getTargetEntity();
        if ($solution instanceof SolutionInterface) {
          $enabled = $ogMenuType === 'quick_links';
          $this->addLink('eif_perspective', $solution, $ogMenuType, 'EIF Perspective', 'entity.node.eif_perspective', enabled: $enabled);
        }
      }
    }
  }

  /**
   * Deletes the EIF perspective menu link when the EIF perspective is deleted.
   */
  #[Hook('meta_entity_delete')]
  public function deleteSolutionMenuLink(MetaEntityInterface $eifPerspective): void {
    $this->menuLinkManager->removeDefinition('eif_perspective', FALSE);
  }

}
