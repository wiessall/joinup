<?php

declare(strict_types=1);

namespace Drupal\custom_page\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Menu\MenuLinkInterface;
use Drupal\Core\Menu\MenuLinkManagerInterface;
use Drupal\Core\Menu\MenuLinkTreeElement;
use Drupal\Core\Menu\MenuLinkTreeInterface;
use Drupal\Core\Menu\MenuTreeParameters;
use Drupal\Core\Render\Element;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Drupal\Core\Utility\LinkGeneratorInterface;
use Drupal\custom_page\Entity\CustomPageInterface;
use Drupal\joinup_group\Entity\GroupInterface;
use Drupal\node\NodeInterface;
use Drupal\og\OgAccessInterface;
use Drupal\og_menu\Form\OgMenuInstanceForm as OriginalOgMenuInstanceForm;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Routing\Matcher\UrlMatcherInterface;

/**
 * Customized form controller for OG Menu instance edit forms.
 *
 * This simplifies the standard menu edit form from OG Menu for the navigation
 * menus of groups. The standard form is intended for webmasters, but the
 * navigation menu is managed by non-technical collection facilitators in the
 * frontend.
 *
 * The following changes are made:
 * - The wording is updated, it mentions 'custom pages' instead of 'menu links'.
 * - The links to the forms of the menu items have been removed. The menu items
 *   are managed automatically whenever a custom page is added to a collection
 *   or removed. Facilitators or moderators should not have access to it.
 * - The operations that originally deal with menu items, are now dealing
 *   directly with custom pages.
 *
 * Most of this code is copied directly from the original form class.
 *
 * @see \Drupal\og_menu\Form\OgMenuInstanceForm
 *
 * @ingroup custom_page
 */
class OgMenuInstanceForm extends OriginalOgMenuInstanceForm {

  use AutowireTrait;

  /**
   * The maximum depth of a menu links tree.
   */
  const MAX_DEPTH = 3;

  public function __construct(
    EntityRepositoryInterface $entity_repository,
    MenuLinkManagerInterface $menu_link_manager,
    MenuLinkTreeInterface $menu_tree,
    LinkGeneratorInterface $link_generator,
    #[Autowire(service: 'og.access')]
    OgAccessInterface $og_access,
    UrlGeneratorInterface $url_generator,
    EntityTypeBundleInfoInterface $entity_type_bundle_info,
    TimeInterface $time,
    #[Autowire(service: 'router.no_access_checks')]
    protected UrlMatcherInterface $url_matcher,
  ) {
    parent::__construct($entity_repository, $menu_link_manager, $menu_tree, $link_generator, $og_access, $url_generator, $entity_type_bundle_info, $time);
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    return match ($this->entity->getType()) {
      'navigation' => $this->buildNavigationForm($form, $form_state),
      'quick_links' => $this->buildQuickLinksForm($form, $form_state),
      default => parent::buildForm($form, $form_state),
    };
  }

  /**
   * Builds a quick links form.
   *
   * @param array $form
   *   Form.
   * @param \Drupal\Core\Form\FormStateInterface $formState
   *   Form's state.
   *
   * @return array
   *   The form structure.
   */
  public function buildQuickLinksForm(array $form, FormStateInterface $formState): array {
    $build = parent::buildForm($form, $formState);

    foreach ($build['links'] as $key => &$link) {
      // Adjust only links.
      if (!str_starts_with($key, 'menu_plugin_id:menu_link_content:')) {
        continue;
      }

      // Disable nesting.
      $link['#attributes']['class'][] = 'tabledrag-root';
      $link['#attributes']['class'][] = 'tabledrag-leaf';
    }

    return $build;
  }

  /**
   * Builds a navigation form.
   *
   * @param array $form
   *   Form.
   * @param \Drupal\Core\Form\FormStateInterface $formState
   *   Form's state.
   *
   * @return array
   *   The form structure.
   */
  public function buildNavigationForm(array $form, FormStateInterface $formState): array {
    $form = EntityForm::buildForm($form, $formState);
    // On entity add, no links are attached yet, so bail out here.
    if ($this->entity->isNew()) {
      return $form;
    }

    // Ensure that menu_overview_form_submit() knows the parents of this form
    // section.
    if (!$formState->has('menu_overview_form_parents')) {
      $formState->set('menu_overview_form_parents', []);
    }

    $form['#attached']['library'][] = 'menu_ui/drupal.menu_ui.adminforms';

    $tree = $this->menuTree->load('ogmenu-' . $this->entity->id(), new MenuTreeParameters());

    // We indicate that a menu administrator is running the menu access check.
    $this->getRequest()->attributes->set('_menu_admin', TRUE);
    $manipulators = [
      ['callable' => 'menu_ui.menu_tree_manipulators:checkAccess'],
      ['callable' => 'menu.default_tree_manipulators:generateIndexAndSort'],
    ];
    $tree = $this->menuTree->transform($tree, $manipulators);
    $this->getRequest()->attributes->set('_menu_admin', FALSE);

    // Determine the delta; the number of weights to be made available.
    $count = function (array $tree) {
      $sum = function ($carry, MenuLinkTreeElement $item) {
        return $carry + $item->count();
      };
      return array_reduce($tree, $sum);
    };
    $delta = max($count($tree), 50);

    $form['links'] = [
      '#type' => 'table',
      '#theme' => 'table__menu_overview',
      '#header' => [
        $this->t('Page'),
        [
          'data' => $this->t('Enabled'),
          'class' => ['checkbox'],
        ],
        $this->t('Weight'),
        [
          'data' => $this->t('Operations'),
          'colspan' => 3,
        ],
      ],
      '#attributes' => [
        'id' => 'menu-overview',
      ],
      '#tabledrag' => [
        [
          'action' => 'match',
          'relationship' => 'parent',
          'group' => 'menu-parent',
          'subgroup' => 'menu-parent',
          'source' => 'menu-id',
          'hidden' => TRUE,
          'limit' => self::MAX_DEPTH - 1,
        ],
        [
          'action' => 'order',
          'relationship' => 'sibling',
          'group' => 'menu-weight',
        ],
      ],
    ];

    $form['links']['#empty'] = $this->t('There are no custom pages yet.');

    $links = $this->buildOverviewTreeForm($tree, $delta);
    foreach (Element::children($links) as $id) {
      if (isset($links[$id]['#item'])) {
        $element = $links[$id];

        $form['links'][$id]['#item'] = $element['#item'];

        // TableDrag: Mark the table row as draggable.
        $form['links'][$id]['#attributes'] = $element['#attributes'];
        $form['links'][$id]['#attributes']['class'][] = 'draggable';

        // TableDrag: Sort the table row according to its existing/configured
        // weight.
        $form['links'][$id]['#weight'] = $element['#item']->link->getWeight();

        // Add special classes to be used for tabledrag.js.
        $element['parent']['#attributes']['class'] = ['menu-parent'];
        $element['weight']['#attributes']['class'] = ['menu-weight'];
        $element['id']['#attributes']['class'] = ['menu-id'];

        $form['links'][$id]['title'] = [
          [
            '#theme' => 'indentation',
            '#size' => $element['#item']->depth - 1,
          ],
          $element['title'],
        ];
        $form['links'][$id]['enabled'] = $element['enabled'];
        $form['links'][$id]['enabled']['#wrapper_attributes']['class'] = [
          'checkbox',
          'menu-enabled',
        ];

        $form['links'][$id]['weight'] = $element['weight'];

        // Operations (dropbutton) column.
        $form['links'][$id]['operations'] = $element['operations'];

        $form['links'][$id]['id'] = $element['id'];
        $form['links'][$id]['parent'] = $element['parent'];

        // Instruct tabledrag JS library to disallow any nesting if needed.
        if (!empty($element['#disable_nesting'])) {
          $form['links'][$id]['#attributes']['class'][] = 'tabledrag-root';
          $form['links'][$id]['#attributes']['class'][] = 'tabledrag-leaf';
          // Remove any indentation on the row.
          unset($form['links'][$id]['title'][0]);
        }
      }
    }

    $form['description'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $this->t('Drag pages to change their order. Disable pages to hide them from the menu.'),
      '#attributes' => ['class' => ['description']],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function buildOverviewTreeForm($tree, $delta): array {
    // If we are not showing the navigation menu for custom pages, fall back to
    // the original method.
    if ($this->entity->getType() !== 'navigation') {
      return parent::buildOverviewTreeForm($tree, $delta);
    }

    $form = &$this->overviewTreeForm;
    $tree_access_cacheability = new CacheableMetadata();
    foreach ($tree as $element) {
      $tree_access_cacheability = $tree_access_cacheability->merge(CacheableMetadata::createFromObject($element->access));

      // Only render accessible links.
      if (!$element->access->isAllowed()) {
        continue;
      }

      /** @var \Drupal\Core\Menu\MenuLinkInterface $link */
      $link = $element->link;
      if ($link) {
        $id = 'menu_plugin_id:' . $link->getPluginId();
        $form[$id]['#item'] = $element;
        $form[$id]['#attributes'] = $link->isEnabled() ? ['class' => ['menu-enabled']] : ['class' => ['menu-disabled']];
        $form[$id]['title'] = Link::fromTextAndUrl($link->getTitle(), $link->getUrlObject())->toRenderable();

        $form[$id]['enabled'] = [
          '#type' => 'checkbox',
          '#title' => $this->t('Enable @title menu link', ['@title' => $link->getTitle()]),
          '#title_display' => 'invisible',
          '#default_value' => $link->isEnabled(),
        ];
        $form[$id]['weight'] = [
          '#type' => 'weight',
          '#delta' => $delta,
          '#default_value' => $link->getWeight(),
          '#title' => $this->t('Weight for @title', ['@title' => $link->getTitle()]),
          '#title_display' => 'invisible',
        ];
        $form[$id]['id'] = [
          '#type' => 'hidden',
          '#value' => $link->getPluginId(),
        ];
        $form[$id]['parent'] = [
          '#type' => 'hidden',
          '#default_value' => $link->getParent(),
        ];

        // Disable nesting of links that are not pointing to nodes.
        $route_info = $this->getRouteInfo($link);
        $is_node_route = !empty($route_info['_route']) && $route_info['_route'] === 'entity.node.canonical';
        if (!$is_node_route) {
          // Force parent value to be empty. This will disable any value
          // submission for this form element, thus disallowing any parent to
          // be selected.
          // @see \Drupal\Core\Menu\MenuLinkInterface::getParent()
          $form[$id]['parent']['#value'] = '';
          // Mark the element so that any nesting operation, both as parent and
          // as children, will be prevented.
          $form[$id]['#disable_nesting'] = TRUE;
        }

        // Build a list of operations. This form is shown to users that do not
        // have access to edit menu links, so instead we are showing links to
        // edit the custom pages directly.
        $operations = [];

        // Skip this if this link is not pointing to the canonical view of a
        // custom page or group, since this means the link has been added
        // manually somehow, probably by an administrator.
        if (
          // The link is a canonical path of a node.
          $is_node_route
          && ($node = $route_info['node'] ?? FALSE)
          && $node instanceof NodeInterface
          // It should be a group or a custom page.
          && ($node instanceof GroupInterface || $node instanceof CustomPageInterface)
        ) {
          $url = $node->toUrl('edit-form');
          if ($url->access()) {
            $operations['edit'] = [
              'title' => $this->t('Edit'),
              'url' => $url,
              'query' => $this->getDestinationArray(),
            ];
          }
          if ($node instanceof CustomPageInterface) {
            $url = $node->toUrl('edit-form');
            if ($url->access()) {
              $operations['delete'] = [
                'title' => $this->t('Delete'),
                'url' => $node->toUrl('delete-form'),
                'query' => $this->getDestinationArray(),
              ];
            }
          }
        }

        $form[$id]['operations'] = [
          '#type' => 'operations',
          '#links' => $operations,
        ];
      }

      if ($element->subtree) {
        $this->buildOverviewTreeForm($element->subtree, $delta);
      }
    }

    $tree_access_cacheability
      ->merge(CacheableMetadata::createFromRenderArray($form))
      ->applyTo($form);

    return $form;
  }

  /**
   * Load route info for the given link.
   */
  private function getRouteInfo(MenuLinkInterface $link): ?array {
    $pathinfo = $link->getUrlObject()->toString();
    // @todo Remove this workaround once this is fixed in core.
    // @see https://www.drupal.org/project/drupal/issues/2548095
    $pathinfo = substr($pathinfo, strlen(base_path()) - 1);

    try {
      return $this->url_matcher->match($pathinfo);
    }
    catch (\Exception) {
      $this->messenger()->addWarning($this->t("Unable to load route for url '%url'", ['%url' => $pathinfo]));
      return NULL;
    }
  }

}
