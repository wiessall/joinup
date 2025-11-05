<?php

declare(strict_types=1);

namespace Drupal\joinup_tour\Plugin\Menu;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Menu\MenuLinkDefault;
use Drupal\Core\Menu\StaticMenuLinkOverridesInterface;
use Drupal\Core\Routing\ResettableStackedRouteMatchInterface;
use Drupal\joinup_tour\TourHelperInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a custom menu link for the tour support menu item.
 */
class TourMenuLink extends MenuLinkDefault {

  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    StaticMenuLinkOverridesInterface $static_override,
    protected readonly TourHelperInterface $tourHelper,
    protected readonly ResettableStackedRouteMatchInterface $currentRouteMatch,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $static_override);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('menu_link.static.overrides'),
      $container->get('joinup_tour.tour_helper'),
      $container->get('current_route_match')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function isEnabled(): bool {
    // If we're administering menus, fallback to the default behaviour.
    $route_path = ltrim($this->currentRouteMatch->getRouteObject()->getPath(), '/');
    if (strpos($route_path, 'admin/structure/menu/') === 0) {
      return parent::isEnabled();
    }

    // The menu link has been disabled by an administrator.
    if (!parent::isEnabled()) {
      return FALSE;
    }

    return $this->tourHelper->currentPageHasTour();
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts(): array {
    return Cache::mergeContexts(['route.name'], parent::getCacheContexts());
  }

}
