<?php

declare(strict_types=1);

namespace Drupal\joinup_core\Plugin\Block;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Menu\Plugin\Block\LocalTasksBlock;
use Drupal\Core\Render\Element;
use Drupal\joinup_core\Traits\GroupPluginCacheTrait;

/**
 * Provides a "Tabs" block to display the local tasks.
 *
 * Drupal has a mechanism that prevents showing the primary tabs if only one
 * option is allowed. This is based on the idea that the active (current) tab is
 * always visible so if there is only 1 tab active, it is the current one and
 * the user does not need to click to go to the current page.
 *
 * In Interoperable Europe Portal, we are hiding off the "View" tab for
 * canonical paths. This can result in cases where there is only 1 tab
 * left but it is not the current one.
 * Thus, we need to override the primary tabs block in order to allow a single
 * tab to be viewed.
 *
 * @Block(
 *   id = "joinup_local_tasks_block",
 *   admin_label = @Translation("Tabs"),
 * )
 */
class JoinupLocalTasksBlock extends LocalTasksBlock {

  use GroupPluginCacheTrait;

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $config = $this->configuration;
    $cacheability = new CacheableMetadata();
    $cacheability->addCacheableDependency($this->localTaskManager);
    $tabs = [
      '#theme' => 'menu_local_tasks',
    ];

    foreach (['primary', 'secondary'] as $level) {
      $route_name = $this->routeMatch->getRouteName();
      // Add only selected levels for the printed output.
      if ($config[$level]) {
        $links = $this->localTaskManager->getLocalTasks($route_name, (int) ($level === 'secondary'));

        // Do not display single tabs if the only tab points to the same page.
        $visible_children = Element::getVisibleChildren($links['tabs']);
        $cacheability->addCacheableDependency($links['cacheability']);
        $count = count($visible_children);

        // Do not display single tabs if the only tab points to the same page.
        if ($count > 1 || ($count === 1 && $visible_children[0] !== $route_name)) {
          $tabs += ["#$level" => $links['tabs']];
        }
      }
    }

    $build = [];
    $cacheability->applyTo($build);

    if (!empty($tabs['#primary']) || !empty($tabs['#secondary'])) {
      $build += $tabs;
    }

    return $build;
  }

}
