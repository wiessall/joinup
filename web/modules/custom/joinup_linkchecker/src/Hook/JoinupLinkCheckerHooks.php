<?php

declare(strict_types=1);

namespace Drupal\joinup_linkchecker\Hook;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\joinup_group\Entity\GroupContentInterface;
use Drupal\joinup_linkchecker\JoinupLinkCheckerLinkStorageSchema;
use Drupal\linkchecker\LinkCheckerLinkInterface;
use Drupal\paragraphs\ParagraphInterface;

/**
 * Hooks implementations for Joinup Link checker module.
 */
final class JoinupLinkCheckerHooks {

  use StringTranslationTrait;

  /**
   * Implements hook_entity_base_field_info().
   *
   * Adds a field to the "Link checker" entity type to reference the potential
   * group of the topmost node containing the link.
   */
  #[Hook('entity_base_field_info')]
  public function groupField(EntityTypeInterface $entityType): array {
    $fields = [];

    if ($entityType->id() === 'linkcheckerlink') {
      $fields['group'] = BaseFieldDefinition::create('og_standard_reference')
        ->setLabel($this->t('Group'))
        ->setCardinality(1)
        ->setSetting('target_type', 'node')
        ->setRequired(FALSE)
        ->setDefaultValue(NULL);
    }

    return $fields;
  }

  /**
   * Implements hook_entity_type_alter().
   */
  #[Hook('entity_type_alter')]
  public function extendLinkCheckerLinkStorageSchema(array &$definitions): void {
    if (isset($definitions['linkcheckerlink'])) {
      // Use the custom storage schema handler class to index the `group` field.
      $definitions['linkcheckerlink']->setHandlerClass('storage_schema', JoinupLinkCheckerLinkStorageSchema::class);
    }
  }

  /**
   * Implements hook_ENTITY_TYPE_presave for the linkcheckerlink entity type.
   */
  #[Hook('linkcheckerlink_presave')]
  public function addGroup(LinkCheckerLinkInterface $link): void {
    $entity = $link->getParentEntity();

    // Climb to the topmost entity containing the link.
    while ($entity instanceof ParagraphInterface && $entity->getParentEntity() !== NULL) {
      $entity = $entity->getParentEntity();
    }

    if ($entity instanceof GroupContentInterface) {
      $link->set('group', $entity->getGroupId());
    }

  }

}
