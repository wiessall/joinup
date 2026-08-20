<?php

declare(strict_types=1);

namespace Drupal\code_of_conduct\Hook;

use Drupal\code_of_conduct\CocEntityCrudHandlerInterface;
use Drupal\code_of_conduct\CocMembershipHandlerInterface;
use Drupal\collection\Entity\CollectionInterface;
use Drupal\collection\Entity\GroupMembership;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\custom_page\Entity\CustomPageInterface;
use Drupal\meta_entity\Entity\MetaEntityInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Code of Conduct  module hook implementations.
 */
class CocHooks {

  public function __construct(
    #[Autowire(service: 'code_of_conduct.crud_handler')]
    readonly protected CocEntityCrudHandlerInterface $cocEntityCrudHandler,
    #[Autowire(service: 'code_of_conduct.membership_handler')]
    readonly protected CocMembershipHandlerInterface $cocMembershipHandler,
  ) {}

  /**
   * Grants, deletes, revokes or restores memberships.
   */
  #[Hook('node_update')]
  public function updateEntity(EntityInterface $entity): void {
    if ($entity instanceof CollectionInterface) {
      $this->cocEntityCrudHandler->onGroupUpdate($entity);
    }
    elseif ($entity instanceof CustomPageInterface) {
      $this->cocEntityCrudHandler->onCodeOfConductUpdate($entity);
    }
  }

  /**
   * Handles the meta entity when created or updated.
   */
  #[Hook('meta_entity_update')]
  public function updateCollectionSettings(MetaEntityInterface $settings): void {
    if ($settings->bundle() !== 'collection_settings') {
      return;
    }
    $this->cocEntityCrudHandler->onGroupSettingsInsertOrUpdate($settings);
  }

  /**
   * Handles the meta entity when deleted.
   */
  #[Hook('meta_entity_delete')]
  public function deleteCollectionSettings(MetaEntityInterface $settings): void {
    if ($settings->bundle() !== 'collection_settings') {
      return;
    }
    $this->cocEntityCrudHandler->onGroupSettingsDelete($settings);
  }

  /**
   * Implements hook_entity_display_build_alter().
   */
  #[Hook('entity_display_build_alter')]
  public function entityDisplayBuildAlter(&$build, $context): void {
    // Views use  \Drupal\views\Entity\Render\EntityFieldRenderer::buildFields
    // in order to build the labels of a reference field. It does this by
    // building a custom entity view component and dynamically generating a
    // rendered view of the entity with the component and extracting the values
    // from it. With the code below, we are stripping the full member role from
    // the roles field in the group membership view and any other place in the
    // website as it mainly is the same role with the default `member`.
    $group_membership = $context['entity'];
    /** @var \Drupal\collection\Entity\GroupMembership $group_membership */
    if (!$group_membership instanceof GroupMembership) {
      return;
    }

    if (empty($build['roles']['#items']) || $build['roles']['#items']->isEmpty()) {
      return;
    }

    $full_member_role_id = $this->cocMembershipHandler->getFullMemberRoleId($group_membership->getGroup());
    /** @var \Drupal\Core\Field\EntityReferenceFieldItemList $items */
    $items = $build['roles']['#items'];
    foreach ($items->getValue() as $delta => $item) {
      if ($item['target_id'] === $full_member_role_id) {
        // We only unset the role from the build, not from the $item object, as
        // that would affect the entity on save - the build is used to determine
        // the values on bulk operations for example.
        unset($build['roles'][$delta]);
      }
    }
  }

}
