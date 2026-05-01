<?php

declare(strict_types=1);

namespace Drupal\joinup_badge\Hook;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\joinup_badge\BadgeService;
use Drupal\joinup_badge\Entity\BadgeAwarding;
use Drupal\joinup_badge\Plugin\Field\FieldType\BadgeFieldItemList;
use Drupal\og\OgMembershipInterface;

/**
 * Hooks for the joinup_badge module.
 */
readonly class BadgeHooks {

  /**
   * The UUID of the Interoperable Europe Community group.
   */
  protected const string IEC_UUID = 'ba527094-00bc-45de-98fb-c732c6c66fec';

  public function __construct(protected BadgeService $badgeService) {}

  /**
   * Implements hook_theme().
   */
  #[Hook('theme')]
  public function theme(): array {
    return [
      'joinup_badge' => [
        'variables' => [
          'badge_type' => NULL,
          'label' => NULL,
          'description' => NULL,
          'icon' => NULL,
          'attributes' => [],
        ],
      ],
    ];
  }

  /**
   * Implements hook_entity_bundle_info_alter().
   */
  #[Hook('entity_bundle_info_alter')]
  public function entityBundleInfoAlter(array &$bundles): void {
    foreach ($this->badgeService->getBadgeFlags() as $flagId => $flag) {
      if (!empty($bundles['flagging'][$flagId])) {
        $bundles['flagging'][$flagId]['class'] = BadgeAwarding::class;
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

    $fields['badges'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(new TranslatableMarkup('Badges'))
      ->setDescription(new TranslatableMarkup('Badges awarded to this user.'))
      ->setSetting('target_type', 'flagging')
      ->setCardinality(FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED)
      ->setComputed(TRUE)
      ->setClass(BadgeFieldItemList::class)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', FALSE)
      ->setDisplayOptions('view', ['type' => 'joinup_badge'])
      ->setDisplayOptions('form', ['region' => 'hidden']);

    return $fields;
  }

  /**
   * Implements hook_og_membership_insert().
   */
  #[Hook('og_membership_insert')]
  public function ogMembershipInsert(OgMembershipInterface $entity): void {
    $group = $entity->getGroup();
    if ($group->uuid() === static::IEC_UUID) {
      $this->badgeService->award('iec_member', $entity->getOwner());
    }
  }

}
