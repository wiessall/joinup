<?php

declare(strict_types=1);

namespace Drupal\joinup_achievement\Hook;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\joinup_achievement\Entity\AchievementFlagging;
use Drupal\joinup_achievement\Plugin\Field\FieldType\AchievementFlagFieldItemList;

/**
 * Hooks for the joinup_achievement module.
 */
class AchievementHooks {

  /**
   * Implements hook_entity_bundle_info_alter().
   */
  #[Hook('entity_bundle_info_alter')]
  public function entityBundleInfoAlter(array &$bundles): void {
    foreach (['iec_member', 'portal_contributor'] as $flag_id) {
      if (!empty($bundles['flagging'][$flag_id])) {
        $bundles['flagging'][$flag_id]['class'] = AchievementFlagging::class;
      }
    }
  }

  /**
   * Implements hook_entity_base_field_info().
   */
  #[Hook('entity_base_field_info')]
  public function entityBaseFieldInfo(EntityTypeInterface $entity_type): array {
    if ($entity_type->id() !== 'user') {
      return [];
    }

    $fields['joinup_achievements'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(new TranslatableMarkup('Achievements'))
      ->setDescription(new TranslatableMarkup('Achievement badges awarded to this user.'))
      ->setSetting('target_type', 'flagging')
      ->setCardinality(FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED)
      ->setComputed(TRUE)
      ->setClass(AchievementFlagFieldItemList::class)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', FALSE)
      ->setDisplayOptions('view', [
        'type' => 'joinup_achievements',
        'label' => 'hidden',
        'weight' => 0,
      ])
      ->setDisplayOptions('form', ['region' => 'hidden']);

    return $fields;
  }

  /**
   * Implements hook_theme().
   */
  #[Hook('theme')]
  public function theme(): array {
    return [
      'joinup_achievement' => [
        'variables' => [
          'icon_id' => '',
          'label' => '',
          'description' => '',
          'attributes' => [],
        ],
      ],
    ];
  }

}
