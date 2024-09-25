<?php

declare(strict_types=1);

namespace Drupal\joinup_group;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\joinup_group\Entity\GroupInterface;
use Drupal\og\OgGroupAudienceHelperInterface;
use Drupal\og_menu\OgMenuInstanceInterface;

/**
 * Provides implementation of "joinup_group.group_menu_manager" service.
 */
class GroupMenuManager implements GroupMenuManagerInterface {

  /**
   * Builds a new custom group menu manager service.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   */
  public function __construct(protected EntityTypeManagerInterface $entityTypeManager) {
  }

  /**
   * {@inheritdoc}
   */
  public function getMenuByGroupId(string|int $groupId, string $menuType): ?OgMenuInstanceInterface {
    $group = $this->entityTypeManager->getStorage('node')->load($groupId);
    if (!$group instanceof GroupInterface) {
      return NULL;
    }

    $properties = [
      'type' => $menuType,
      OgGroupAudienceHelperInterface::DEFAULT_FIELD => $groupId,
    ];
    $storage = $this->entityTypeManager->getStorage('ogmenu_instance');
    if ($instances = $storage->loadByProperties($properties)) {
      return reset($instances);
    }

    return NULL;
  }

}
