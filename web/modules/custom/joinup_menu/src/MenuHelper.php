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
use Drupal\contact_information\ContactInformationEmailConfirmInterface;
use Drupal\email_confirmer\EmailConfirmerManagerInterface;
use Drupal\joinup_collection\JoinupCollectionHelper;
use Drupal\joinup_group\Entity\GroupInterface;
use Drupal\menu_link_content\MenuLinkContentInterface;
use Drupal\og\OgGroupAudienceHelper;
use Exception;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Helper methods for working with menus.
 */
class MenuHelper implements MenuHelperInterface {

  use StringTranslationTrait;
  use RedirectDestinationTrait;

  /**
   * Constructs an instance of the MenuHelper service.
   *
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger
   *   The logger channel factory.
   * @param \Drupal\Core\Menu\MenuLinkTreeInterface $menuLinkTree
   *   The menu link tree service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The request stack.
   * @param \Drupal\Core\Path\PathMatcherInterface $pathMatcher
   *   The path matcher.
   * @param \Drupal\email_confirmer\EmailConfirmerManagerInterface $emailConfirmer
   *   The email confirmer service.
   */
  public function __construct(
    protected LoggerChannelFactoryInterface $logger,
    protected MenuLinkTreeInterface $menuLinkTree,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected RequestStack $requestStack,
    protected PathMatcherInterface $pathMatcher,
    protected EmailConfirmerManagerInterface $emailConfirmer
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getBclAccountMenu(): array {
    $tree = $this->getMenuLinkElements('account');

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
      'id' => 'get-started',
      'label' => $this->t('Get started'),
      'popover' => $popover,
    ];

    // Generate a link to the Joinup collection, to show as
    // the "About us" link in the hamburger menu for anonymous users.
    $rdf_storage = $this->entityTypeManager->getStorage('rdf_entity');
    try {
      $joinup_collection = $rdf_storage->load(JoinupCollectionHelper::getCollectionId());
      if (!$joinup_collection instanceof CollectionInterface) {
        throw new Exception();
      }
      $url = $joinup_collection->toUrl();
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
      // However, in scope of the Ventuno theme the About Us link is considered
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
    $tree = $this->getMenuLinkElements('navbar');

    return array_map(function (MenuLinkTreeElement $element): array {
      $link = [
        'link' => $element->link->getUrlObject(),
        'label' => $element->link->getTitle(),
        'children' => [],
      ];
      if ($element->hasChildren) {
        foreach ($element->subtree as $child) {
          $link['children'][] = [
            'link' => $child->link->getUrlObject(),
            'label' => $child->link->getTitle(),
          ];
        }
      }

      return $link;
    }, $tree);
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
    $tree = $this->menuLinkTree->load($menu, new MenuTreeParameters());
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

  /**
   * {@inheritdoc}
   */
  public function syncQuickLinksMenuItems(GroupInterface $group): void {
    if (!isset($group->original)) {
      throw new \RuntimeException('This method should only be called during a group update hook. There is no original values available.');
    }

    foreach (MenuHelperInterface::IDENTIFIER_MAPPINGS as $fieldName => $identifier) {
      if (!$group->hasField($fieldName)) {
        continue;
      }

      // Skip if the field is not changed.
      if ($group->get($fieldName)->equals($group->original->get($fieldName))) {
        continue;
      }

      $menuLink = $this->getQuickLinksMenuLink($group->id(), $identifier);
      if (!$menuLink instanceof MenuLinkContentInterface) {
        continue;
      }

      // If the field was emptied, set the menu link to 'internal:#' and disable
      // it.
      if ($group->get($fieldName)->isEmpty()) {
        $menuLink->set('link', ['uri' => 'internal:#']);
        $menuLink->set('enabled', FALSE);
      }
      else {
        $link = $this->getQuickLinksUrlForField($group, $fieldName);
        $link = $link->isExternal() ? $link->getUri() : 'internal:/' . $link->getInternalPath();
        $menuLink->set('link', $link);
        $menuLink->set('enabled', $this->isLinkEnabled($group, $fieldName));
      }

      $menuLink->save();
    }
  }

  /**
   * Returns the quick links menu link.
   *
   * @param \Drupal\joinup_group\Entity\GroupInterface $groupId
   *   The group entity.
   * @param string $identifier
   *   The identifier.
   *
   * @return \Drupal\menu_link_content\MenuLinkContentInterface|null
   *   The menu link or NULL if not found.
   */
  protected function getQuickLinksMenuLink(string $groupId, string $identifier): ?MenuLinkContentInterface {
    $menus = $this->entityTypeManager
      ->getStorage('ogmenu_instance')
      ->loadByProperties([
        OgGroupAudienceHelper::DEFAULT_FIELD => $groupId,
        'type' => 'quick_links',
      ]);
    $menu = reset($menus);
    if (empty($menu)) {
      return NULL;
    }

    $menuLinkStorage = $this->entityTypeManager
      ->getStorage('menu_link_content');
    $menuLinkIds = $menuLinkStorage->getQuery()
      ->accessCheck(FALSE)
      ->condition('menu_name', 'ogmenu-' . $menu->id())
      ->condition('internal_identifier', $identifier)
      ->execute();

    $menuLink = $menuLinkStorage->load(reset($menuLinkIds));
    if (!$menuLink instanceof MenuLinkContentInterface) {
      return NULL;
    }

    return $menuLink;
  }

  /**
   * {@inheritdoc}
   */
  public function enableContactQuickLink(string $groupId): void {
    if ($menuLink = $this->getQuickLinksMenuLink($groupId, MenuHelperInterface::INTERNAL_IDENTIFIER_CONTACT_US)) {
      $menuLink->set('enabled', TRUE);
      $menuLink->save();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getQuickLinksUrlForField(GroupInterface $group, string $fieldName): Url {
    switch ($fieldName) {
      case 'field_ar_access_url':
      case 'field_is_landing_page':
        $accessUrl = $group->$fieldName->uri;
        return $accessUrl ? Url::fromUri($accessUrl) : Url::fromUri('internal:#');

      case 'field_is_documentation':
        if ($documentationFile = $group->field_is_documentation->entity) {
          return $documentationFile->url();
        }
        return Url::fromUri('internal:#');

      case 'field_ar_contact_information':
      case 'field_is_contact_information':
        return Url::fromRoute('email_contact.form', [
          'entity_type' => $group->getEntityTypeId(),
          'entity' => $group->id(),
          'field_name' => $fieldName,
        ]);

      default:
        throw new \RuntimeException(sprintf('The field "%s" is not supported.', $fieldName));
    }
  }

  /**
   * Returns whether the link should be enabled.
   *
   * @param \Drupal\joinup_group\Entity\GroupInterface $group
   *   The group entity.
   * @param string $fieldName
   *   The field name.
   *
   * @return bool
   *   Whether the link should be enabled.
   */
  protected function isLinkEnabled(GroupInterface $group, string $fieldName): bool {
    switch ($fieldName) {
      case 'field_ar_access_url':
      case 'field_is_landing_page':
        return !empty($group->$fieldName->uri);

      case 'field_is_documentation':
        return !empty($group->field_is_documentation->entity);

      case 'field_ar_contact_information':
      case 'field_is_contact_information':
        // @todo The service should be injected.
        foreach ($group->$fieldName->referencedEntities() as $contactInformation) {
          if ($contactInformation->field_ci_email->value && $this->emailConfirmer->getConfirmation($contactInformation->field_ci_email->value, TRUE, ContactInformationEmailConfirmInterface::REALM)) {
            return TRUE;
          }
        }
        return FALSE;

      default:
        throw new \RuntimeException(sprintf('The field "%s" is not supported.', $fieldName));
    }
  }

}
