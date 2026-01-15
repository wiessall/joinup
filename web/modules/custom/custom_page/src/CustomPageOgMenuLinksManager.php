<?php

declare(strict_types=1);

namespace Drupal\custom_page;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Menu\MenuLinkManagerInterface;
use Drupal\Core\Url;
use Drupal\custom_page\Entity\CustomPageInterface;
use Drupal\joinup_group\GroupMenuManagerInterface;
use Drupal\menu_link_content\MenuLinkContentInterface;
use Drupal\node\NodeInterface;
use Drupal\og\OgGroupAudienceHelperInterface;
use Drupal\og_menu\OgMenuInstanceInterface;
use Drupal\search_api\Plugin\search_api\datasource\ContentEntityTrackingManager;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Manages the OG Menu links of custom pages.
 */
readonly class CustomPageOgMenuLinksManager implements CustomPageOgMenuLinksManagerInterface {

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected MenuLinkManagerInterface $menuLinkManager,
    protected GroupMenuManagerInterface $groupMenuManager,
    #[Autowire(service: 'search_api.entity_datasource.tracking_manager')]
    protected ContentEntityTrackingManager $trackingManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getChildren(NodeInterface $customPage, string $menuType): array {
    $menuLinkContentStorage = $this->entityTypeManager->getStorage('menu_link_content');
    $nodeStorage = $this->entityTypeManager->getStorage('node');

    $this->verifyCustomPage($customPage);
    $children = [];
    if ($ogMenuInstance = $this->getOgMenuInstanceByCustomPage($customPage, $menuType)) {
      $menuName = "ogmenu-{$ogMenuInstance->id()}";
      // Collect the IDs of links to the custom page.
      $mids = $menuLinkContentStorage->getQuery()->accessCheck(FALSE)
        ->condition('bundle', 'menu_link_content')
        ->condition('menu_name', $menuName)
        ->condition('link.uri', "entity:node/{$customPage->id()}")
        ->execute();
      if ($mids) {
        $parents = [];
        /** @var \Drupal\menu_link_content\MenuLinkContentInterface $menuLink */
        foreach ($menuLinkContentStorage->loadMultiple($mids) as $menuLink) {
          $parents[] = $menuLink->getPluginId();
        }
        if ($parents) {
          $childrenIds = $menuLinkContentStorage->getQuery()->accessCheck(FALSE)
            ->condition('bundle', 'menu_link_content')
            ->condition('menu_name', $menuName)
            ->condition('parent', $parents, 'IN')
            ->execute();
          if ($childrenIds) {
            foreach ($menuLinkContentStorage->loadMultiple($childrenIds) as $menuLink) {
              if ($uri = $menuLink->link->uri) {
                try {
                  $url = Url::fromUri($uri);
                  if ($url->isRouted() && $url->getRouteName() === 'entity.node.canonical' && ($parameters = $url->getRouteParameters()) && !empty($parameters['node'])) {
                    if ($node = $nodeStorage->load($parameters['node'])) {
                      $children[$parameters['node']] = $node;
                    }
                  }
                }
                catch (\Exception $exception) {
                  // Fail silently.
                }
              }
            }
          }
        }
      }
    }
    return $children;
  }

  /**
   * {@inheritdoc}
   */
  public function addLink(NodeInterface $customPage, string $menuType, array $properties = []): CustomPageOgMenuLinksManagerInterface {
    $menuLinkContentStorage = $this->entityTypeManager->getStorage('menu_link_content');
    $this->verifyCustomPage($customPage);
    if ($ogMenuInstance = $this->getOgMenuInstanceByCustomPage($customPage, $menuType)) {
      $properties = $properties + [
        'title' => $customPage->label(),
        'menu_name' => 'ogmenu-' . $ogMenuInstance->id(),
        'link' => ['uri' => 'entity:node/' . $customPage->id()],
        'enabled' => TRUE,
      ];

      $menuLinkContentStorage->create($properties)->save();
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function moveLinks(NodeInterface $customPage, $groupId): CustomPageOgMenuLinksManagerInterface {
    $menuLinkContentStorage = $this->entityTypeManager->getStorage('menu_link_content');
    $this->verifyCustomPage($customPage);
    foreach (['navigation', 'quick_links'] as $ogMenuInstanceId) {
      if ($sourceOgMenuInstance = $this->getOgMenuInstanceByCustomPage($customPage, $ogMenuInstanceId)) {
        if ($targetOgMenuInstance = $this->groupMenuManager->getMenuByGroupId($groupId, $ogMenuInstanceId)) {
          $sourceMenuName = "ogmenu-{$sourceOgMenuInstance->id()}";
          // Collect the IDs of links to the custom page.
          $mids = $menuLinkContentStorage->getQuery()->accessCheck(FALSE)
            ->condition('bundle', 'menu_link_content')
            ->condition('menu_name', $sourceMenuName)
            ->condition('link.uri', "entity:node/{$customPage->id()}")
            ->execute();
          if ($mids) {
            $targetMenuName = "ogmenu-{$targetOgMenuInstance->id()}";
            /** @var \Drupal\menu_link_content\MenuLinkContentInterface $menuLink */
            foreach ($menuLinkContentStorage->loadMultiple($mids) as $menuLink) {
              // Change the OG menu instance of each link.
              $menuLink->set('menu_name', $targetMenuName)->save();
            }
          }
        }
      }
    }

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function updateLink(MenuLinkContentInterface $menuLink): void {
    // Do a quick check to see if the menu link entity's ableness has changed.
    if ($menuLink->isEnabled() === $menuLink->original->isEnabled()) {
      return;
    }

    // Ignore external links.
    $urlObject = $menuLink->getUrlObject();
    if ($urlObject->isExternal()) {
      return;
    }

    $routeParameters = $menuLink->getUrlObject()->getRouteParameters();
    if (isset($routeParameters['node'])) {
      $customPage = $this->entityTypeManager->getStorage('node')->load($routeParameters['node']);
      if ($customPage instanceof CustomPageInterface) {
        if ($customPage->hasGroup()) {
          $this->trackingManager->trackEntityChange($customPage->getGroup());
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function deleteLinks(NodeInterface $customPage): CustomPageOgMenuLinksManagerInterface {
    $menuLinkContentStorage = $this->entityTypeManager->getStorage('menu_link_content');
    $this->verifyCustomPage($customPage);

    foreach (['navigation', 'quick_links'] as $ogMenuInstanceId) {
      if ($ogMenuInstance = $this->getOgMenuInstanceByCustomPage($customPage, $ogMenuInstanceId)) {
        $menuName = "ogmenu-{$ogMenuInstance->id()}";
        foreach ($customPage->uriRelationships() as $rel) {
          $url = $customPage->toUrl($rel);
          // Delete all MenuLinkContent links that point to this entity route.
          if ($result = $this->menuLinkManager->loadLinksByRoute($url->getRouteName(), $url->getRouteParameters())) {
            /** @var \Drupal\menu_link_content\Plugin\Menu\MenuLinkContent $instance */
            foreach ($result as $id => $instance) {
              if ($instance->getMenuName() === $menuName && $instance->isDeletable() && str_starts_with($id, 'menu_link_content:')) {
                $instance->deleteLink();
                // Search for children of deleted menu link.
                $mids = $menuLinkContentStorage->getQuery()->accessCheck(FALSE)
                  ->condition('parent', "menu_link_content:{$instance->getDerivativeId()}")
                  ->execute();
                if ($mids) {
                  // Remove the relationship to the deleted parent menu link.
                  /** @var \Drupal\menu_link_content\MenuLinkContentInterface $menuLinkContent */
                  foreach ($menuLinkContentStorage->loadMultiple($mids) as $menuLinkContent) {
                    $menuLinkContent->set('parent', NULL)->save();
                  }
                }
              }
            }
          }
        }
      }
    }
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getOgMenuInstanceByCustomPage(NodeInterface $customPage, string $menuType): ?OgMenuInstanceInterface {
    $this->verifyCustomPage($customPage);
    if ($groupId = $customPage->get(OgGroupAudienceHelperInterface::DEFAULT_FIELD)->target_id) {
      return $this->groupMenuManager->getMenuByGroupId($groupId, $menuType);
    }
    return NULL;
  }

  /**
   * Checks that the passed in node is a custom page.
   *
   * @param \Drupal\node\NodeInterface $customPage
   *   The node to check.
   */
  protected function verifyCustomPage(NodeInterface $customPage): void {
    $bundle = $customPage->bundle();
    if ($bundle !== 'custom_page') {
      throw new \InvalidArgumentException("The node is not a custom page, but a '$bundle'.");
    }
  }

}
