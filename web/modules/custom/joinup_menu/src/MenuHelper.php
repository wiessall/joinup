<?php

declare(strict_types=1);

namespace Drupal\joinup_menu;

use Drupal\collection\Entity\CollectionInterface;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\VariationCacheFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Menu\MenuLinkTreeElement;
use Drupal\Core\Menu\MenuLinkTreeInterface;
use Drupal\Core\Path\PathMatcherInterface;
use Drupal\Core\Routing\RedirectDestinationTrait;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\joinup_collection\JoinupCollectionHelper;
use Drupal\og\OgGroupAudienceHelperInterface;
use Drupal\og_menu\OgMenuInstanceInterface;
use Drupal\rdf_sync\RdfSyncMapperInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Helper methods for working with menus.
 */
class MenuHelper implements MenuHelperInterface {

  use StringTranslationTrait;
  use RedirectDestinationTrait;

  public function __construct(
    protected readonly LoggerChannelFactoryInterface $logger,
    protected readonly MenuLinkTreeInterface $menuLinkTree,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly RequestStack $requestStack,
    protected readonly PathMatcherInterface $pathMatcher,
    protected readonly RdfSyncMapperInterface $mapper,
    #[Autowire(service: 'variation_cache_factory')]
    protected readonly VariationCacheFactoryInterface $variationCacheFactory,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getBclAccountMenu(CacheableMetadata $cacheability): array {
    $cacheability->addCacheTags(['config:system.menu.account']);
    $cacheability->addCacheContexts(['route.menu_active_trails:account']);
    $links = [];
    foreach ($this->getMenuLinkElements('account') as $element) {
      $links[] = $this->buildBclLink($element);
    }
    return $links;
  }

  /**
   * {@inheritdoc}
   */
  public function getBclAnonymousMenu(CacheableMetadata $cacheability): array {
    $anonymous_links = [];

    $anonymous_links[] = [
      'label' => $this->t('Home'),
      'link' => Url::fromRoute('<front>'),
      'hide_on_desktop' => TRUE,
    ];

    $anonymous_links = array_merge($anonymous_links, $this->getBclLandingPageLinks($cacheability));

    $cas_login_link = Url::fromRoute('cas.login', [], [
      'query' => $this->getDestinationArray(),
    ]);

    $anonymous_links[] = [
      'label' => $this->t('Sign in'),
      'link' => $cas_login_link,
      'attributes' => ['class' => ['sign-in-link']],
    ];

    $popover = [
      'cta' => [
        // Even though the link claims to lead to an account creation form, we
        // are linking to the EU Login portal. Account creation is not handled
        // inside the Joinup domain.
        'link' => $cas_login_link,
        'attributes' => 'tabindex="0"',
        'text' => $this->t('Create an account'),
      ],
      'description' => $this->t('As a signed-in user you can create content, become a member of a community, receive notifications on your favourite solutions and topics, and access all other features available on the platform.'),
    ];
    $anonymous_links[] = [
      'id' => 'get-started',
      'label' => $this->t('Get started'),
      'popover' => $popover,
    ];

    if ($about_us_link = $this->getBclAboutUsLink($cacheability)) {
      $anonymous_links[] = $about_us_link;
    }

    return $anonymous_links;
  }

  /**
   * Returns the "About us" link pointing to the Joinup collection landing page.
   *
   * @param \Drupal\Core\Cache\CacheableMetadata $cacheability
   *   Collects cacheability for the "About us" link.
   *
   * @return array|null
   *   The "About us" link in the BCL header template format, or NULL when the
   *   Joinup collection does not exist.
   */
  protected function getBclAboutUsLink(CacheableMetadata $cacheability): ?array {
    return $this->cachedBuild(['joinup_menu', 'about_us_link'], ['languages:language_interface'], $cacheability, function (CacheableMetadata $build_cacheability): ?array {
      $build_cacheability->addCacheTags(['joinup_collection:exists']);
      $joinup_collection = $this->mapper->getEntityByUri(JoinupCollectionHelper::JOINUP_COLLECTION_URI);
      if (!$joinup_collection instanceof CollectionInterface) {
        $this->logger->get('joinup_menu')->warning('"About us" link could not be rendered because the Interoperable Europe Portal collection is not defined or doesn\'t have an About page.');
        return NULL;
      }
      $build_cacheability->addCacheableDependency($joinup_collection);
      return [
        'label' => $this->t('About us'),
        'link' => $joinup_collection->toUrl(),
        'hide_on_desktop' => TRUE,
      ];
    });
  }

  /**
   * {@inheritdoc}
   */
  public function getBclLandingPageLinks(CacheableMetadata $cacheability): array {
    return $this->cachedBuild(['joinup_menu', 'landing_page_links'], [
      'languages:language_interface',
      'user.permissions',
      'route.menu_active_trails:navbar',
    ], $cacheability, function (CacheableMetadata $build_cacheability): array {
      $build_cacheability->addCacheTags(['config:system.menu.navbar']);
      $links = [];
      foreach ($this->getMenuLinkElements('navbar') as $element) {
        $link = $this->buildBclLink($element, TRUE);
        $link['children'] = [];
        foreach ($element->subtree as $child) {
          $link['children'][] = $this->buildBclLink($child, TRUE);
        }
        $links[] = $link;
      }
      return $links;
    });
  }

  /**
   * {@inheritdoc}
   */
  public function getOgMenuByGroup(string $menuType, string $groupId): ?OgMenuInstanceInterface {
    /** @var \Drupal\og_menu\OgMenuInstanceInterface[] $ogMenuInstances */
    $ogMenuInstances = $this->entityTypeManager->getStorage('ogmenu_instance')->loadByProperties([
      'type' => $menuType,
      OgGroupAudienceHelperInterface::DEFAULT_FIELD => $groupId,
    ]);

    return $ogMenuInstances ? reset($ogMenuInstances) : NULL;
  }

  /**
   * Returns a context-varied cached menu build, computing it on a cache miss.
   *
   * @param string[] $keys
   *   The cache keys identifying the build.
   * @param string[] $contexts
   *   The cache context tokens the build varies by.
   * @param \Drupal\Core\Cache\CacheableMetadata $cacheability
   *   Collects cacheability for the build: the $contexts and whatever the
   *   builder declares are added to it.
   * @param callable $builder
   *   A callback that receives a cache metadata object to populate and returns
   *   the build result to cache.
   *
   * @return mixed
   *   The cached, or freshly computed, build result.
   */
  protected function cachedBuild(array $keys, array $contexts, CacheableMetadata $cacheability, callable $builder): mixed {
    $initial = (new CacheableMetadata())->setCacheContexts($contexts);
    $cacheability->addCacheableDependency($initial);
    $cache = $this->variationCacheFactory->get('default');
    if ($cached = $cache->get($keys, $initial)) {
      $cacheability->addCacheableDependency($cached->data['cacheability']);
      return $cached->data['build'];
    }

    $build_cacheability = clone $initial;
    $build = $builder($build_cacheability);
    $data = ['build' => $build, 'cacheability' => $build_cacheability];
    $cache->set($keys, $data, $build_cacheability, $initial);
    $cacheability->addCacheableDependency($build_cacheability);
    return $build;
  }

  /**
   * Maps a menu tree element to the BCL header template link format.
   *
   * @param \Drupal\Core\Menu\MenuLinkTreeElement $element
   *   The menu tree element to map.
   * @param bool $set_active_class
   *   Whether to let Drupal mark the link active on the matching path.
   *
   * @return array
   *   The link in the BCL header template format.
   */
  protected function buildBclLink(MenuLinkTreeElement $element, bool $set_active_class = FALSE): array {
    $url = $element->link->getUrlObject();
    if ($set_active_class) {
      $url->setOption('set_active_class', TRUE);
    }
    return [
      'link' => $url,
      'label' => $element->link->getTitle(),
      'in_active_trail' => $element->inActiveTrail,
    ];
  }

  /**
   * Gets menu link elements.
   *
   * @param string $menu
   *   Menu machine name.
   *
   * @return \Drupal\Core\Menu\MenuLinkTreeElement[]
   *   Menu links elements.
   */
  protected function getMenuLinkElements(string $menu): array {
    $parameters = $this->menuLinkTree->getCurrentRouteMenuTreeParameters($menu)->onlyEnabledLinks();
    $tree = $this->menuLinkTree->load($menu, $parameters);
    $manipulators = [
      ['callable' => 'menu.default_tree_manipulators:checkAccess'],
      ['callable' => 'menu.default_tree_manipulators:generateIndexAndSort'],
    ];
    $tree = $this->menuLinkTree->transform($tree, $manipulators);
    return array_filter($tree, function (MenuLinkTreeElement $element): bool {
      // Hide inaccessible links, unpublished links, as well as the 'EU Login
      // info' link. The latter is only intended for anonymous users.
      // @see joinup_eulogin_preprocess_menu()
      return $element->access instanceof AccessResultInterface &&
        $element->access->isAllowed() &&
        $element->link->isEnabled() &&
        $element->link->getPluginId() !== 'joinup_eulogin.eu_login_info';
    });
  }

}
