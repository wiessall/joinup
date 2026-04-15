<?php

declare(strict_types=1);

namespace Drupal\user_achievement\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBundleBase;
use Drupal\Core\Entity\Attribute\ConfigEntityType;
use Drupal\Core\Entity\EntityDeleteForm;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Entity\Routing\AdminHtmlRouteProvider;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\user_achievement\Form\AchievementTypeForm;

/**
 * Defines the Achievement type configuration entity.
 *
 * Each achievement type defines a badge: its human-readable title, the tooltip
 * description shown on the user profile, and the icon used to represent it.
 */
#[ConfigEntityType(
  id: 'achievement_type',
  label: new TranslatableMarkup('Achievement type'),
  label_collection: new TranslatableMarkup('Achievement types'),
  label_singular: new TranslatableMarkup('achievement type'),
  label_plural: new TranslatableMarkup('achievement types'),
  config_prefix: 'type',
  entity_keys: [
    'id' => 'id',
    'label' => 'label',
    'uuid' => 'uuid',
  ],
  handlers: [
    'list_builder' => EntityListBuilder::class,
    'route_provider' => [
      'html' => AdminHtmlRouteProvider::class,
    ],
    'form' => [
      'add' => AchievementTypeForm::class,
      'edit' => AchievementTypeForm::class,
      'delete' => EntityDeleteForm::class,
    ],
  ],
  links: [
    'add-form' => '/admin/config/people/achievements/types/add',
    'edit-form' => '/admin/config/people/achievements/types/{achievement_type}',
    'delete-form' => '/admin/config/people/achievements/types/{achievement_type}/delete',
    'collection' => '/admin/config/people/achievements/types',
  ],
  admin_permission: 'administer user achievements',
  bundle_of: 'user_achievement',
  label_count: [
    'singular' => '@count achievement type',
    'plural' => '@count achievement types',
  ],
  config_export: [
    'id',
    'label',
    'uuid',
    'description',
    'icon',
  ],
)]
final class AchievementType extends ConfigEntityBundleBase implements AchievementTypeInterface {

  /**
   * The machine name of this achievement type.
   */
  protected string $id;

  /**
   * The human-readable name of this achievement type.
   */
  protected string $label;

  /**
   * The tooltip description shown on the user profile.
   */
  protected string $description = '';

  /**
   * The Ventuno sprite icon ID for this badge (e.g. "award", "patch-check").
   */
  protected string $icon = '';

  /**
   * {@inheritdoc}
   */
  public function getDescription(): string {
    return $this->description;
  }

  /**
   * {@inheritdoc}
   */
  public function getIcon(): string {
    return $this->icon;
  }

  /**
   * {@inheritdoc}
   */
  public function setIcon(string $icon): static {
    $this->icon = $icon;
    return $this;
  }

  /**
   * Returns the list of icon IDs available from the Ventuno theme sprite.
   *
   * @return string[]
   *   Icon IDs, suitable for use in `<use xlink:href="sprite.svg#id">`.
   */
  public static function getAvailableIcons(): array {
    return [
      'archive',
      'archive-fill',
      'arrow-left',
      'arrow-repeat',
      'arrow-right',
      'award',
      'bookmark',
      'bookmark-check-fill',
      'box-arrow-up-right',
      'calendar',
      'calendar-event',
      'card-list',
      'caret-down-fill',
      'chat',
      'chat-dots',
      'chat-left-text',
      'chat-square',
      'check-circle',
      'check-circle-fill',
      'chevron-bar-left',
      'chevron-bar-right',
      'chevron-down',
      'chevron-left',
      'chevron-right',
      'chevron-up',
      'clipboard2-check',
      'clock-history',
      'code-slash',
      'download',
      'envelope-fill',
      'exclamation-circle-fill',
      'exclamation-lg',
      'exclamation-triangle-fill',
      'eye',
      'facebook',
      'file-earmark',
      'file-earmark-check',
      'file-earmark-medical',
      'file-earmark-play',
      'file-earmark-plus',
      'file-earmark-post',
      'file-earmark-richtext',
      'file-earmark-text',
      'file-earmark-zip',
      'file-font',
      'file-x',
      'filter',
      'folder2-open',
      'gear-fill',
      'geo-alt',
      'github',
      'hand-thumbs-up',
      'hand-thumbs-up-fill',
      'info-circle',
      'info-circle-fill',
      'journal-text',
      'justify',
      'lightning-charge',
      'link-45deg',
      'linkedin',
      'mastodon',
      'newspaper',
      'patch-check',
      'patch-exclamation',
      'pencil-square',
      'people',
      'person-circle',
      'person-workspace',
      'pin-angle-fill',
      'play-btn',
      'plus',
      'question-lg',
      'reply',
      'rss',
      'search',
      'star',
      'three-dots',
      'trash',
      'twitter',
      'type',
      'vimeo',
      'x',
      'x-circle-fill',
      'x-lg',
      'x-square-fill',
      'youtube',
    ];
  }

}
