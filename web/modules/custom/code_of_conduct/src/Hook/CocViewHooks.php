<?php

declare(strict_types=1);

namespace Drupal\code_of_conduct\Hook;

use Drupal\code_of_conduct\CocMembershipHandlerInterface;
use Drupal\collection\Entity\GroupMembership;
use Drupal\Core\Hook\Attribute\Hook;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Code of Conduct module hook implementations for views.
 */
class CocViewHooks {

  public function __construct(
    #[Autowire(service: 'code_of_conduct.membership_handler')]
    readonly protected CocMembershipHandlerInterface $cocMembershipHandler,
  ) {}

  /**
   * Implements hook_entity_display_build_alter().
   */
  #[Hook('entity_display_build_alter')]
  public function entityDisplayBuildAlter(array &$build, array $context): void {
    // Views use \Drupal\views\Entity\Render\EntityFieldRenderer::buildFields
    // to build the labels of a reference field. It does this by building a
    // custom entity view component and dynamically generating a rendered view
    // of the entity with the component, then extracting the values from it.
    // With the code below, we strip the full member role from the roles field
    // in the group membership view and anywhere else on the website, as it is
    // essentially the same role as the default `member`.
    $groupMembership = $context['entity'];
    if (!$groupMembership instanceof GroupMembership) {
      return;
    }

    if (empty($build['roles']['#items']) || $build['roles']['#items']->isEmpty()) {
      return;
    }

    $fullMemberRoleId = $this->cocMembershipHandler->getFullMemberRoleId($groupMembership->getGroup());
    /** @var \Drupal\Core\Field\EntityReferenceFieldItemList $items */
    $items = $build['roles']['#items'];
    foreach ($items->getValue() as $delta => $item) {
      if ($item['target_id'] === $fullMemberRoleId) {
        // We only unset the role from the build, not from the $item object, as
        // that would affect the entity on save - the build is used to determine
        // the values on bulk operations for example.
        unset($build['roles'][$delta]);
      }
    }
  }

}
