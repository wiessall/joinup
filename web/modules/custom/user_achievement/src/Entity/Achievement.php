<?php

declare(strict_types=1);

namespace Drupal\user_achievement\Entity;

use Drupal\Core\Entity\Attribute\ContentEntityType;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\ContentEntityDeleteForm;
use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityViewBuilder;
use Drupal\Core\Entity\Routing\DefaultHtmlRouteProvider;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\user\EntityOwnerTrait;

/**
 * Defines the Achievement entity class.
 *
 * Represents the allocation of a badge to a specific user. The badge
 * definition (title, description, icon) lives in the AchievementType config
 * entity, which is the bundle of this entity.
 */
#[ContentEntityType(
  id: 'user_achievement',
  label: new TranslatableMarkup('Achievement'),
  label_collection: new TranslatableMarkup('Achievements'),
  label_singular: new TranslatableMarkup('achievement'),
  label_plural: new TranslatableMarkup('achievements'),
  entity_keys: [
    'id' => 'id',
    'bundle' => 'type',
    'uuid' => 'uuid',
    'owner' => 'uid',
  ],
  handlers: [
    'view_builder' => EntityViewBuilder::class,
    'list_builder' => EntityListBuilder::class,
    'route_provider' => [
      'html' => DefaultHtmlRouteProvider::class,
    ],
    'form' => [
      'add' => ContentEntityForm::class,
      'edit' => ContentEntityForm::class,
      'delete' => ContentEntityDeleteForm::class,
    ],
  ],
  links: [
    'canonical' => '/admin/config/people/achievements/{achievement_type}/{user_achievement}',
    'add-form' => '/admin/config/people/achievements/{achievement_type}/add',
    'edit-form' => '/admin/config/people/achievements/{achievement_type}/{user_achievement}/edit',
    'delete-form' => '/admin/config/people/achievements/{achievement_type}/{user_achievement}/delete',
    'collection' => '/admin/config/people/achievements',
  ],
  admin_permission: 'administer user achievements',
  bundle_entity_type: 'achievement_type',
  bundle_label: new TranslatableMarkup('Achievement type'),
  base_table: 'user_achievement',
  label_count: [
    'singular' => '@count achievement',
    'plural' => '@count achievements',
  ],
  field_ui_base_route: 'entity.achievement_type.edit_form',
)]
class Achievement extends ContentEntityBase implements AchievementInterface {

  use EntityOwnerTrait;

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);
    $fields += static::ownerBaseFieldDefinitions($entity_type);

    $fields['uid'] = BaseFieldDefinition::create('entity_reference')
      ->setLabel(new TranslatableMarkup('User'))
      ->setDescription(new TranslatableMarkup('The user this achievement was awarded to.'))
      ->setSetting('target_type', 'user')
      ->setDefaultValueCallback(self::class . '::getDefaultEntityOwner')
      ->setDisplayConfigurable('form', TRUE)
      ->setDisplayConfigurable('view', TRUE);

    $fields['created'] = BaseFieldDefinition::create('created')
      ->setLabel(new TranslatableMarkup('Awarded on'))
      ->setDescription(new TranslatableMarkup('The time the achievement was awarded.'))
      ->setDisplayConfigurable('view', TRUE);

    return $fields;
  }

}
