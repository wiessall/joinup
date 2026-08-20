<?php

declare(strict_types=1);

namespace Drupal\ventuno_helper\Plugin\Block;

use Drupal\collection\Entity\CollectionInterface;
use Drupal\Component\Utility\Html;
use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Block\BlockManagerInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\joinup_bookmark\JoinupBookmarkInterface;
use Drupal\joinup_collection\JoinupCollectionHelper;
use Drupal\rdf_sync\RdfSyncMapperInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the non-menu header navigation items.
 *
 * The navbar and account menus are provided by core menu blocks; this block
 * renders the remaining BCL navbar items that are not menus: the search box,
 * the bookmark link and, for anonymous users, the sign in and get started
 * items. It emits the <li> elements that are composed into the navbar by the
 * header navigation region.
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
    protected readonly JoinupBookmarkInterface $bookmark,
    protected readonly RouteMatchInterface $routeMatch,
    protected readonly BlockManagerInterface $blockManager,
    protected readonly RdfSyncMapperInterface $mapper,
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
      $container->get('joinup_bookmark.bookmark'),
      $container->get('current_route_match'),
      $container->get('plugin.manager.block'),
      $container->get('rdf_sync.mapper'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts(): array {
    return Cache::mergeContexts(parent::getCacheContexts(), [
      'route.name',
      'user.roles:authenticated',
      'url.path',
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $cacheability = new CacheableMetadata();
    $logged_in = $this->currentUser->isAuthenticated();

    $items = [];

    $mobile_links = $this->getLandingPageLinks($cacheability);

    if (!$logged_in) {
      $anonymous_links = $this->getAnonymousActions();
      $items[] = [
        'breakpoint' => 'all',
        'links' => [$anonymous_links['sign_in']],
      ];
      $items[] = [
        'breakpoint' => 'xl',
        'links' => [$anonymous_links['get_started']],
      ];

      array_unshift($mobile_links, [
        'element' => [
          '#type' => 'link',
          '#title' => $this->t('Home'),
          '#url' => Url::fromRoute('<front>'),
        ],
      ]);
      $mobile_links[] = [
        'element' => $this->buildSignInLink(),
      ];
      if ($about_us_link = $this->getAboutUsLink($cacheability)) {
        $mobile_links[] = $about_us_link;
      }
    }

    if ($mobile_links) {
      $items[] = [
        'type' => 'mmenu',
        'id' => 'navbar-toggler',
        'icon' => 'justify',
        'label' => $this->t('Toggle menu'),
        'links' => $mobile_links,
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
      '#theme' => 'joinup_header_navigation',
      '#items' => $items,
      '#search' => $search,
    ];
    $cacheability->applyTo($build);
    return $build;
  }

  /**
   * Builds the anonymous header actions in the BCL header format.
   *
   * @return array
   *   The header actions, keyed by 'sign_in' and 'get_started'.
   */
  protected function getAnonymousActions(): array {
    return [
      'sign_in' => [
        'element' => $this->buildSignInLink(['nav-link', 'nav-link--underline', 'sign-in-link']),
      ],
      'get_started' => [
        'id' => 'get-started',
        'label' => $this->t('Get started'),
        'popover' => [
          'cta' => [
            'element' => [
              '#theme' => 'joinup_header_get_started_cta',
              '#url' => Url::fromRoute('cas.login'),
            ],
          ],
          'description' => $this->t('As a signed-in user you can create content, become a member of a community, receive notifications on your favourite solutions and topics, and access all other features available on the platform.'),
        ],
      ],
    ];
  }

  /**
   * Builds a "Sign in" link to the CAS login.
   *
   * The post login redirect destination is appended client side by the
   * 'ventuno_helper/destination-link' library, so this link stays cacheable.
   *
   * @param string[] $classes
   *   The classes for the anchor.
   *
   * @return array
   *   The render array for the sign in link.
   */
  protected function buildSignInLink(array $classes = []): array {
    $element = [
      '#type' => 'link',
      '#title' => $this->t('Sign in'),
      '#url' => Url::fromRoute('cas.login'),
      '#attributes' => ['data-destination' => TRUE],
    ];
    if ($classes) {
      $element['#attributes']['class'] = $classes;
    }
    return $element;
  }

  /**
   * Returns the "About us" link pointing to the Joinup collection landing page.
   *
   * @param \Drupal\Core\Cache\CacheableMetadata $cacheability
   *   Collects the cacheability of the "About us" link.
   *
   * @return array|null
   *   The "About us" link in the BCL header format, or NULL when the Joinup
   *   collection does not exist.
   */
  protected function getAboutUsLink(CacheableMetadata $cacheability): ?array {
    $cacheability->addCacheTags(['joinup_collection:exists']);
    $joinup_collection = $this->mapper->getEntityByUri(JoinupCollectionHelper::JOINUP_COLLECTION_URI);
    if (!$joinup_collection instanceof CollectionInterface) {
      return NULL;
    }
    $cacheability->addCacheableDependency($joinup_collection);
    return [
      'element' => [
        '#type' => 'link',
        '#title' => $this->t('About us'),
        '#url' => $joinup_collection->toUrl(),
      ],
    ];
  }

  /**
   * Builds the navbar (landing pages) menu links for the mobile drawer.
   *
   * @param \Drupal\Core\Cache\CacheableMetadata $cacheability
   *   Collects the cacheability of the navbar menu.
   *
   * @return array
   *   The navbar menu links in the BCL header format.
   */
  protected function getLandingPageLinks(CacheableMetadata $cacheability): array {
    $build = $this->blockManager->createInstance('system_menu_block:navbar', [
      'level' => 1,
      'depth' => 2,
      'expand_all_items' => TRUE,
    ])->build();
    $cacheability->addCacheableDependency(CacheableMetadata::createFromRenderArray($build));
    return $this->buildMenuLinks($build['#items'] ?? []);
  }

  /**
   * Converts a menu tree into nested BCL header links.
   *
   * @param array $items
   *   The menu items as returned by the menu block, each with an optional
   *   'below' array of child items.
   *
   * @return array
   *   The links in the BCL header format, each carrying its render array under
   *   the 'element' key, with child links nested under the 'links' key so the
   *   mobile drawer can render them as a second level.
   */
  protected function buildMenuLinks(array $items): array {
    $links = [];
    foreach ($items as $item) {
      $element = [
        '#type' => 'link',
        '#title' => $item['title'],
        '#url' => $item['url'],
      ];
      if (!empty($item['in_active_trail'])) {
        $element['#attributes']['class'][] = 'is-active';
      }
      $link = ['element' => $element];
      if (!empty($item['below'])) {
        $link['links'] = $this->buildMenuLinks($item['below']);
      }
      $links[] = $link;
    }
    return $links;
  }

  /**
   * Renders the search view's exposed form for the header search box.
   *
   * @param \Drupal\Core\Cache\CacheableMetadata $cacheability
   *   Collects the cacheability of the rendered search form.
   *
   * @return array
   *   The search form render array, or an empty array when the search view is
   *   not available.
   */
  protected function buildSearch(CacheableMetadata $cacheability): array {
    $plugin_id = 'views_exposed_filter_block:search-page_1';
    if (!$this->blockManager->hasDefinition($plugin_id)) {
      return [];
    }
    $build = $this->blockManager->createInstance($plugin_id)->build();
    if (isset($build['keys'])) {
      $build['keys']['#id'] = Html::getUniqueId('search-box');
    }
    $cacheability->addCacheableDependency(CacheableMetadata::createFromRenderArray($build));
    return $build;
  }

}
