<?php

declare(strict_types=1);

namespace Drupal\joinup_core;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Theme\ThemeManagerInterface;

/**
 * Default implementation of 'joinup_core.local_task_links_helper' service.
 */
class LocalTaskLinksHelper implements LocalTaskLinksHelperInterface {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The theme manager service.
   *
   * @var \Drupal\Core\Theme\ThemeManagerInterface
   */
  protected ThemeManagerInterface $themeManager;

  /**
   * Constructs a new service instance.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\Core\Theme\ThemeManagerInterface $theme_manager
   *   The theme manager service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, ThemeManagerInterface $theme_manager) {
    $this->entityTypeManager = $entity_type_manager;
    $this->themeManager = $theme_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function allowHorizontalTabs(): AccessResultInterface {
    $active_theme = $this->themeManager->getActiveTheme();
    $theme_info = $active_theme->getExtension()->info;
    if (empty($theme_info['joinup']['menu_local_tasks']['horizontal_tabs_block'])) {
      // Not a theme aware of horizontal tabs vs. three dots menu detection.
      return AccessResult::neutral();
    }
    return $this->entityTypeManager->getStorage('block')
      ->load($theme_info['joinup']['menu_local_tasks']['horizontal_tabs_block'])
      ->access('view', NULL, TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function allowThreeDotsMenu(): AccessResultInterface {
    $horizontal_tabs_access = $this->allowHorizontalTabs();
    return AccessResult::forbiddenIf($horizontal_tabs_access->isAllowed())
      ->addCacheableDependency($horizontal_tabs_access);
  }

}
