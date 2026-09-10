<?php

declare(strict_types=1);

namespace Drupal\joinup_linkchecker\Hook;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\joinup_group\Entity\GroupContentInterface;
use Drupal\joinup_group\Entity\GroupInterface;
use Drupal\joinup_group\Exception\MissingGroupException;
use Drupal\joinup_linkchecker\JoinupLinkCheckerLinkStorageSchema;
use Drupal\linkchecker\LinkCheckerLinkInterface;

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
      $fields['group'] = BaseFieldDefinition::create('entity_reference')
        ->setTargetEntityTypeId('joinup_log')
        ->setLabel($this->t('Group'))
        ->setCardinality(1)
        ->setSetting('target_type', 'node')
        ->setRequired(FALSE)
        ->setDefaultValue(NULL)
        ->setInitialValue(NULL);
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
    $entity = $link->getTopMostParentEntity();

    if ($entity instanceof GroupInterface) {
      $link->set('group', $entity->id());
    }
    elseif ($entity instanceof GroupContentInterface) {
      try {
        $link->set('group', $entity->getGroup());
      }
      catch (MissingGroupException) {
        // Custom page is GroupContentInterface node, but it might also act as
        // standalone content.
      }
    }
  }

  /**
   * Implements hook_views_data_alter().
   */
  #[Hook('views_data_alter')]
  public function viewsDataAlter(array &$data): void {
    // @see \Drupal\joinup_linkchecker\Plugin\views\filter\JoinupLinkCheckerGroupAutocomplete
    $data['linkchecker_link']['group']['filter']['id'] = 'joinup_linkchecker_group_autocomplete';
  }

}
