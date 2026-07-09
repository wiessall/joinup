<?php

declare(strict_types=1);

namespace Drupal\joinup_menu\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Theme\ThemeManagerInterface;
use Drupal\joinup_bookmark\JoinupBookmarkInterface;
use Drupal\joinup_menu\MenuHelperInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the non-menu header navigation items.
 *
 * The navbar and account menus are provided by core menu blocks; this block
 * renders the remaining BCL navbar items that are not menus: the search box,
 * the bookmark link and, for anonymous users, the sign in / get started /
 * about us items. It emits the <li> elements that are composed into the navbar
 * by the header navigation region.
 */
#[Block(
  id: 'joinup_header_navigation',
  admin_label: new TranslatableMarkup('Header navigation extras'),
  category: new TranslatableMarkup('Joinup'),
)]
class HeaderNavigationBlock extends BlockBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected readonly AccountProxyInterface $currentUser,
    protected readonly MenuHelperInterface $menuHelper,
    protected readonly JoinupBookmarkInterface $bookmark,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly RouteMatchInterface $routeMatch,
    protected readonly ThemeManagerInterface $themeManager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): HeaderNavigationBlock {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('current_user'),
      $container->get('joinup_menu.menu_helper'),
      $container->get('joinup_bookmark.bookmark'),
      $container->get('entity_type.manager'),
      $container->get('current_route_match'),
      $container->get('theme.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $cacheability = new CacheableMetadata();
    $logged_in = $this->currentUser->isAuthenticated();
    $cacheability->addCacheContexts(['route', 'user.roles']);

    $items = [];

    if (!$logged_in) {
      $anonymous_links = $this->menuHelper->getBclAnonymousMenu($cacheability);
      $cacheability->addCacheContexts(['url.path']);
      $items[] = [
        'type' => 'mmenu',
        'breakpoint' => 'xl',
        'id' => 'navbar-toggler',
        'icon' => ['name' => 'justify'],
        'label' => $this->t('Toggle menu'),
        'links' => $anonymous_links,
      ];
    }

    $bookmark_link = $this->bookmark->getBookmarkLink();
    if ($bookmark_link) {
      $items[] = [
        'breakpoint' => 'all',
        'label' => $this->t('Bookmarks'),
        'links' => [$bookmark_link + ['order' => 1]],
      ];
    }

    $hide_searchbar = $this->routeMatch->getRouteName() === 'view.search.page_1';
    $search = [];
    if (!$hide_searchbar) {
      $search = $this->buildSearch($cacheability);
      $items[] = [
        'type' => 'search',
        'breakpoint' => '',
        'classes' => ['order-2 dropdown-search'],
        'id' => 'search-box-dropdown',
        'icon' => ['name' => 'search'],
        'label' => $this->t('Toggle search box'),
        'links' => $search,
      ];
    }

    $build = [
      '#theme' => 'joinup_header',
      '#items' => $items,
      '#search' => $search,
    ];
    $cacheability->applyTo($build);
    return $build;
  }

  /**
   * Renders the search block placed in the active theme's search bar region.
   *
   * @param \Drupal\Core\Cache\CacheableMetadata $cacheability
   *   Collects the cacheability of the rendered search block.
   *
   * @return array
   *   The search block render array, or an empty array when it is not placed.
   */
  protected function buildSearch(CacheableMetadata $cacheability): array {
    $blocks = $this->entityTypeManager->getStorage('block')->loadByProperties([
      'theme' => $this->themeManager->getActiveTheme()->getName(),
      'region' => 'search_bar',
    ]);
    if (!$blocks) {
      return [];
    }
    $block = reset($blocks);
    $cacheability->addCacheableDependency($block);
    return $this->entityTypeManager->getViewBuilder('block')->view($block);
  }

}
