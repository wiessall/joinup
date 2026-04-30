<?php

declare(strict_types=1);

namespace Drupal\joinup_badge\Hook;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\joinup_badge\BadgeService;
use Drupal\joinup_badge\Entity\BadgeFlagging;
use Drupal\joinup_badge\InteroperableEuropeCommunityMembership;
use Drupal\joinup_badge\Plugin\Field\FieldType\BadgeFlagFieldItemList;
use Drupal\og\OgMembershipInterface;

/**
 * Hooks for the joinup_badge module.
 */
class BadgeHooks {

  /**
   * Constructs a BadgeHooks object.
   */
  public function __construct(
    protected readonly BadgeService $badgeService,
  ) {}

  /**
   * Implements hook_entity_bundle_info_alter().
   */
  #[Hook('entity_bundle_info_alter')]
  public function entityBundleInfoAlter(array &$bundles): void {
    foreach ($this->badgeService->getBadgeFlagIds() as $flagId) {
      if (!empty($bundles['flagging'][$flagId])) {
        $bundles['flagging'][$flagId]['class'] = BadgeFlagging::class;
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

    $fields['joinup_badges'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(new TranslatableMarkup('Badges'))
      ->setDescription(new TranslatableMarkup('Badges awarded to this user.'))
      ->setSetting('target_type', 'flagging')
      ->setCardinality(FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED)
      ->setComputed(TRUE)
      ->setClass(BadgeFlagFieldItemList::class)
      ->setDisplayConfigurable('view', TRUE)
      ->setDisplayConfigurable('form', FALSE)
      ->setDisplayOptions('view', [
        'type' => 'entity_reference_label',
        'label' => 'hidden',
        'weight' => 0,
        'settings' => ['link' => FALSE],
      ])
      ->setDisplayOptions('form', ['region' => 'hidden']);

    return $fields;
  }

  /**
   * Implements hook_og_membership_insert().
   */
  #[Hook('og_membership_insert')]
  public function ogMembershipInsert(OgMembershipInterface $entity): void {
    $group = $entity->getGroup();
    if ($group->uuid() === InteroperableEuropeCommunityMembership::UUID) {
      $this->badgeService->award('iec_member', $entity->getOwner());
    }
  }

}
