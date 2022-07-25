<?php

declare(strict_types = 1);

namespace Drupal\joinup_menu;

use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Menu\MenuLinkTreeElement;
use Drupal\Core\Menu\MenuLinkTreeInterface;
use Drupal\Core\Menu\MenuTreeParameters;
use Drupal\Core\Path\PathMatcherInterface;
use Drupal\Core\Routing\RedirectDestinationTrait;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\collection\Entity\CollectionInterface;
use Drupal\interoperable_europe\InteroperableEuropeCollectionInterface;
use Drupal\joinup_collection\JoinupCollectionHelper;
use Exception;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Helper methods for working with menus.
 */
class MenuHelper implements MenuHelperInterface {

  use StringTranslationTrait;
  use RedirectDestinationTrait;

  /**
   * The logger channel factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected LoggerChannelFactoryInterface $logger;

  /**
   * The menu link tree service.
   *
   * @var \Drupal\Core\Menu\MenuLinkTreeInterface
   */
  protected MenuLinkTreeInterface $menuLinkTree;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected RequestStack $requestStack;

  /**
   * The path matcher.
   *
   * @var \Drupal\Core\Path\PathMatcherInterface
   */
  protected PathMatcherInterface $pathMatcher;

  /**
   * Constructs an instance of the MenuHelper service.
   *
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger
   *   The logger channel factory.
   * @param \Drupal\Core\Menu\MenuLinkTreeInterface $menu_link_tree
   *   The menu link tree service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   * @param \Drupal\Core\Path\PathMatcherInterface $path_matcher
   *   The path matcher.
   */
  public function __construct(LoggerChannelFactoryInterface $logger, MenuLinkTreeInterface $menu_link_tree, EntityTypeManagerInterface $entity_type_manager, RequestStack $request_stack, PathMatcherInterface $path_matcher) {
    $this->logger = $logger;
    $this->menuLinkTree = $menu_link_tree;
    $this->entityTypeManager = $entity_type_manager;
    $this->requestStack = $request_stack;
    $this->pathMatcher = $path_matcher;
  }

  /**
   * {@inheritdoc}
   */
  public function getBclAccountMenu(): array {
    $tree = $this->menuLinkTree->load('account', new MenuTreeParameters());
    $manipulators = [
      ['callable' => 'menu.default_tree_manipulators:checkAccess'],
      ['callable' => 'menu.default_tree_manipulators:generateIndexAndSort'],
    ];
    $tree = $this->menuLinkTree->transform($tree, $manipulators);
    $tree = array_filter($tree, function (MenuLinkTreeElement $element): bool {
      // Hide inaccessible links, unpublished links, as well as the 'EU Login
      // info' link. The latter is only intended for anonymous users.
      // @see joinup_eulogin_preprocess_menu()
      return $element->access instanceof AccessResultInterface &&
        $element->access->isAllowed() &&
        $element->link->isEnabled() &&
        $element->link->getPluginId() !== 'joinup_eulogin.eu_login_info';
    });

    return array_map(function (MenuLinkTreeElement $element): array {
      return [
        'link' => $element->link->getUrlObject(),
        'label' => $element->link->getTitle(),
      ];
    }, $tree);
  }

  /**
   * {@inheritdoc}
   */
  public function getBclAnonymousMenu(): array {
    // Populate the links shown to anonymous users.
    $anonymous_links = [];

    // Add a link to the homepage, only to be shown in the hamburger menu.
    $anonymous_links[] = [
      'label' => $this->t('Home'),
      'link' => Url::fromRoute('<front>'),
      'hide_on_desktop' => TRUE,
    ];

    // Include the links to the interoperability landing pages.
    $anonymous_links = array_merge($anonymous_links, $this->getBclLandingPageLinks());

    // Generate a link to EU Login.
    // Get return URL from current page.
    $cas_login_link = Url::fromRoute('cas.login', [], [
      'query' => $this->getDestinationArray(),
    ]);

    $anonymous_links[] = [
      'label' => $this->t('Sign in'),
      'link' => $cas_login_link,
    ];

    // Generate a link button that opens the "Get started" popover.
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
      'label' => $this->t('Get started'),
      'popover' => $popover,
    ];

    // Generate a link to the About page of the Joinup collection, to show as
    // the "About us" link in the hamburger menu for anonymous users.
    $rdf_storage = $this->entityTypeManager->getStorage('rdf_entity');
    try {
      $joinup_collection = $rdf_storage->load(JoinupCollectionHelper::getCollectionId());
      if (!$joinup_collection instanceof CollectionInterface) {
        throw new Exception();
      }
      $url = $joinup_collection->toUrl('about-page');
      $anonymous_links[] = [
        'label' => $this->t('About us'),
        'link' => $url,
        'hide_on_desktop' => TRUE,
      ];
    }
    catch (Exception $e) {
      // The Joinup collection could not be loaded or the link to the About page
      // could not be generated. This should not crash the page since the
      // collection is considered to be optional. It is for example possible for
      // a project to reuse the Joinup open source code without hosting a
      // "Joinup" collection.
      // However in scope of the Ventuno theme the About Us link is considered
      // to be an important element, so we log a warning to alert the webmaster
      // that corrective action is required.
      $this->logger->get('joinup_menu')->warning('"About us" link could not be rendered because the Joinup collection is not defined or doesn\'t have an About page.');
    }

    return $anonymous_links;
  }

  /**
   * {@inheritdoc}
   */
  public function getBclLandingPageLinks(): array {
    // Include links to 'Interoperable Europe' and 'Interoperability Solutions'.
    $landing_page_links = [];
    foreach ([
      InteroperableEuropeCollectionInterface::COLLECTION_ENTITY_ID => 'rdf_entity',
      JoinupCollectionHelper::INTEROPERABILITY_SOLUTIONS_ENTITY_ID => 'node',
    ] as $id => $entity_type_id) {
      try {
        $entity = $this->entityTypeManager->getStorage($entity_type_id)->load($id);
        $landing_page_links[] = [
          'label' => $entity->label(),
          'link' => $entity->toUrl(),
        ];
      }
      catch (Exception $e) {
        // The custom pages cannot be loaded. This should not crash the page
        // since these pages might not exist in projects that reuse the Joinup
        // open source project.
      }
    }
    return $landing_page_links;
  }

}
