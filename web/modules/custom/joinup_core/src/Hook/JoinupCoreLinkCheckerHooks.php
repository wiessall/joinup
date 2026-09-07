<?php

declare(strict_types=1);

namespace Drupal\joinup_core\Hook;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\joinup_core\JoinupCoreLinkCheckerLinkStorageSchema;

/**
 * Hooks implementations related to the "Link checker" module.
 */
final class JoinupCoreLinkCheckerHooks {

  use StringTranslationTrait;

  /**
   * Implements hook_entity_base_field_info().
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
      $definitions['linkcheckerlink']->setHandlerClass('storage_schema', JoinupCoreLinkCheckerLinkStorageSchema::class);
    }
  }

}
