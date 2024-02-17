<?php

declare(strict_types=1);

namespace Drupal\joinup_group;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\og\OgGroupAudienceHelperInterface;
use Drupal\og_menu\OgMenuInstanceInterface;
use Drupal\rdf_entity\Entity\Rdf;

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
  public function getMenuByGroupId(string $groupId, string $menuType): ?OgMenuInstanceInterface {
    if (!Rdf::load($groupId)) {
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
