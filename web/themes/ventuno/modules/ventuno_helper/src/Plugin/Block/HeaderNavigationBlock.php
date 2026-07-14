<?php

declare(strict_types=1);

namespace Drupal\ventuno_helper\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Block\BlockManagerInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RedirectDestinationInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\joinup_bookmark\JoinupBookmarkInterface;
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
    protected readonly RedirectDestinationInterface $redirectDestination,
    protected readonly JoinupBookmarkInterface $bookmark,
    protected readonly RouteMatchInterface $routeMatch,
    protected readonly BlockManagerInterface $blockManager,
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
      $container->get('redirect.destination'),
      $container->get('joinup_bookmark.bookmark'),
      $container->get('current_route_match'),
      $container->get('plugin.manager.block'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $cacheability = new CacheableMetadata();
    $logged_in = $this->currentUser->isAuthenticated();
    $cacheability->addCacheContexts(['route.name', 'user.roles:authenticated']);

    $items = [];

    if (!$logged_in) {
      $anonymous_links = $this->getAnonymousActions($cacheability);
      $items[] = [
        'breakpoint' => 'all',
        'links' => [$anonymous_links['sign_in']],
      ];
      $items[] = [
        'breakpoint' => 'xl',
        'links' => [$anonymous_links['get_started']],
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
      '#items' => $items,
      '#search' => $search,
    ];
    $cacheability->applyTo($build);
    return $build;
  }

  /**
   * Builds the anonymous header actions in the BCL header format.
   *
   * @param \Drupal\Core\Cache\CacheableMetadata $cacheability
   *   Collects the cacheability of the anonymous actions.
   *
   * @return array
   *   The header actions, keyed by 'sign_in' and 'get_started'.
   */
  protected function getAnonymousActions(CacheableMetadata $cacheability): array {
    // Sign in and account creation both open EU Login and return the user to
    // the page they started from, so these links vary per request URL.
    $cacheability->addCacheContexts(['url.path', 'url.query_args']);
    $cas_login_link = Url::fromRoute('cas.login', [], [
      'query' => $this->redirectDestination->getAsArray(),
    ]);

    return [
      'sign_in' => [
        'label' => $this->t('Sign in'),
        'link' => $cas_login_link,
        'attributes' => ['class' => ['sign-in-link']],
      ],
      'get_started' => [
        'id' => 'get-started',
        'label' => $this->t('Get started'),
        'popover' => [
          'cta' => [
            'link' => $cas_login_link,
            'attributes' => 'tabindex="0"',
            'text' => $this->t('Create an account'),
          ],
          'description' => $this->t('As a signed-in user you can create content, become a member of a community, receive notifications on your favourite solutions and topics, and access all other features available on the platform.'),
        ],
      ],
    ];
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
    $cacheability->addCacheableDependency(CacheableMetadata::createFromRenderArray($build));
    return $build;
  }

}
