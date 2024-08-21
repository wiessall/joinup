<?php

declare(strict_types=1);

namespace Drupal\joinup_group\Plugin\views\argument_validator;

use Drupal\joinup_group\Entity\GroupInterface;
use Drupal\meta_entity\Entity\MetaEntity;

/**
 * Defines an argument validator plugin for groups.
 *
 * @ViewsArgumentValidator(
 *   id = "joinup_group_announcements_enabled",
 *   title = @Translation("Group announcements"),
 *   entity_type = "rdf_entity",
 * )
 */
class GroupAnnouncementsEnabledValidator extends JoinupGroupUserPermissionValidator {

  /**
   * {@inheritdoc}
   */
  public function validateArgument($arg): bool {
    if (!$arg || !($group = $this->entityTypeManager->getStorage('node')->load($arg)) || !$group instanceof GroupInterface || !$group->isPublished()) {
      return FALSE;
    }
    $settings = MetaEntity::loadOrCreate("{$group->bundle()}_settings", $group);
    if (!$settings->get('announcements_enabled')->value) {
      return FALSE;
    }
    return parent::validateArgument($arg);
  }

}
