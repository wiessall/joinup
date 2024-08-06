<?php

declare(strict_types=1);

namespace Drupal\joinup_group_content_management\Plugin\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Entity\EntityReferenceSelection\SelectionPluginManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Render\Renderer;
use Drupal\Core\Session\AccountInterface;
use Drupal\custom_page\CustomPageOgMenuLinksManagerInterface;
use Drupal\menu_link_content\MenuLinkContentInterface;
use Drupal\node\NodeInterface;
use Drupal\og_menu\OgMenuInstanceInterface;
use Drupal\rdf_entity\Entity\Rdf;
use Drupal\sparql_entity_storage\UriEncoder;
use Drupal\views_bulk_operations\Action\ViewsBulkOperationsActionBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a VBO action that changes the group for nodes.
 *
 * This will also move the node into the OG Menu for the 'navigation' menu.
 *
 * The business requirements for this action call for special handling of custom
 * pages which necessitated this action to be placed into a separate module,
 * since it would introduce a circular dependency between the `joinup_group` and
 * `custom_page` modules.
 *
 * @todo Implement an event subscriber inside `ChangeGroupAction` that allows to
 *   alter the list of actions. This will allow us to move all the custom logic
 *   into a listener in the `custom_page` module and solve the circular
 *   dependency. The action can then be moved inside `joinup_group` and this
 *   module is no longer needed.
 *
 * @see https://citnet.tech.ec.europa.eu/CITnet/jira/browse/ISAICP-5969
 *
 * @Action(
 *   id = "joinup_change_group",
 *   label = @Translation("Move to other group"),
 *   type = "node",
 *   confirm = FALSE,
 *   pass_context = TRUE,
 *   pass_view = FALSE,
 *   requirements = {
 *      "_permission" = "vbo joinup change group action",
 *   },
 * )
 */
class ChangeGroupAction extends ViewsBulkOperationsActionBase implements ContainerFactoryPluginInterface {

  /**
   * The corresponding OG menu instance.
   */
  protected OgMenuInstanceInterface $ogMenuInstance;

  /**
   * A list UUIDs of menu links of top-level custom pages.
   */
  protected array $topLevelCustomPages = [];

  /**
   * A list of menu links entities, keyed by custom page node ID.
   *
   * @var \Drupal\menu_link_content\MenuLinkContentInterface[]
   */
  protected array $pageMenuLinks = [];

  /**
   * Constructs a new 'joinup_change_group' action plugin.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityReferenceSelection\SelectionPluginManagerInterface $selectionPluginManager
   *   The selection plugin manager service.
   * @param \Drupal\Core\Render\Renderer $renderer
   *   The renderer service.
   * @param \Drupal\custom_page\CustomPageOgMenuLinksManagerInterface $customPageOgMenuLinksManager
   *   The custom pages OG menu links manager service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected SelectionPluginManagerInterface $selectionPluginManager,
    protected Renderer $renderer,
    protected CustomPageOgMenuLinksManagerInterface $customPageOgMenuLinksManager,
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('plugin.manager.entity_reference_selection'),
      $container->get('renderer'),
      $container->get('custom_page.og_menu_links_manager'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE): AccessResultInterface|bool {
    // The access is limited at the view level.
    return $return_as_object ? AccessResult::allowed() : TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function executeMultiple(array $nodes): void {
    $message = array_fill_keys(['error', 'warning', 'status'], []);
    $groupId = $this->getConfiguration()['group'];
    if (!$group = Rdf::load($groupId)) {
      throw new \RuntimeException("Cannot load RDF entity with ID $groupId");
    }

    // Order the nodes, so that the custom page parent nodes are first. This is
    // important in order to check if the parent of the 2nd tier custom pages
    // were moved too.
    $this->reorder($nodes);

    $initialNids = array_map(function (NodeInterface $node) {
      return $node->id();
    }, $nodes);

    foreach (['navigation', 'quick_links'] as $menuType) {
      $nodesToProcess = $nodes;
      /** @var \Drupal\node\NodeInterface[] $nodes */
      while ($nodesToProcess) {
        $node = array_shift($nodesToProcess);
        $isCustomPage = $node->bundle() === 'custom_page';

        // It might be a parent custom page.
        if ($isCustomPage && $children = $this->customPageOgMenuLinksManager->getChildren($node, $menuType)) {
          // Filter out child nodes that are already in the main set.
          $children = array_diff_key($children, array_flip($initialNids));
          array_walk($children, function (NodeInterface $child): void {
            // Flag this as a child.
            $child->isChild = TRUE;
          });
          // Add children in the flow, just after the parent which is currently
          // being processed.
          $nodesToProcess = array_merge($children, $nodesToProcess);
        }

        $args = [
          '@title' => $node->label(),
          ':url' => $node->toUrl()->toString(),
          '@type' => $node->type->entity->label(),
          '@group' => $group->label(),
          ':group_url' => $group->toUrl()->toString(),
        ];

        // Avoid trying to re-assign the group if the node is already in the
        // group.
        if ($node->get('og_audience')->target_id === $groupId) {
          continue;
        }

        try {
          // Prevent notification dispatching.
          // @see joinup_notification_dispatch_notification()
          $node->skip_notification = TRUE;
          $node->set('og_audience', $groupId)->save();
          if (empty($node->isChild)) {
            $message['status'][] = [
              [
                '#markup' => $this->t('@type <a href=":url">@title</a> group was changed to <a href=":group_url">@group</a>.', $args),
              ],
            ];
          }
          else {
            $lastMessage =& $message['status'][count($message['status']) - 1];
            if (!isset($lastMessage[1])) {
              $lastMessage[1] = [
                '#theme' => 'item_list',
                '#items' => [],
              ];
            }
            $lastMessage[1]['#items'][] = $this->t('Child @type <a href=":url">@title</a> group was changed too.', $args);
          }

          // Check the user selected 2nd tier custom pages.
          if ($isCustomPage && empty($node->isChild)) {
            if ($parentId = $this->pageMenuLinks[$node->id()]->getParentId()) {
              [, $uuid] = explode(':', $parentId, 2);
              // If the custom page has a parent that wasn't selected by the
              // user, the page will be moved as top-level page. This is
              // accomplished by removing the corresponding OG menu link parent
              // reference.
              if (!in_array($uuid, $this->topLevelCustomPages)) {
                $this->pageMenuLinks[$node->id()]->set('parent', NULL)->save();
              }
            }
          }
        }
        catch (\Exception $e) {
          $message['error'][] = $this->t("Error while trying to change the group for '@title'.", $args);
        }
      }
    }

    foreach ($message as $type => $messageGroup) {
      if ($messageGroup) {
        $list = [
          '#theme' => 'item_list',
          '#items' => $messageGroup,
        ];
        $this->messenger()->addMessage($this->renderer->render($list), $type);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function execute(?NodeInterface $node = NULL): void {
    $this->executeMultiple([$node]);
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $formState): array {
    /** @var \Drupal\Core\Entity\EntityReferenceSelection\SelectionPluginBase $selection */
    $selection = $this->selectionPluginManager->createInstance('default:rdf_entity', [
      'target_type' => 'rdf_entity',
      'target_bundles' => ['collection', 'solution'],
    ]);
    $selectionSettings = $selection->getConfiguration() + [
      'match_operator' => 'CONTAINS',
    ];

    $form['group'] = [
      '#title' => $this->t('Select the destination collection or solution'),
      '#type' => 'entity_autocomplete',
      '#target_type' => 'rdf_entity',
      '#selection_handler' => 'default:rdf_entity',
      '#selection_settings' => $selectionSettings,
      '#maxlength' => 2048,
      '#required' => TRUE,
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $formState): void {
    /** @var \Drupal\Core\Url $url */
    $url = $formState->get('views_bulk_operations')['redirect_url'];
    if ($url->isRouted() && ($parameters = $url->getRouteParameters()) && !empty($parameters['rdf_entity'])) {
      /** @var \Drupal\rdf_entity\RdfInterface $sourceEntity */
      $sourceEntity = Rdf::load(UriEncoder::decodeUrl($parameters['rdf_entity']));
      if ($sourceEntity->id() === $formState->getValue('group')) {
        $formState->setErrorByName('group', $this->t("The destination group is the same as the source group: %group. Please, select other destination group.", [
          '%group' => $sourceEntity->label(),
        ]));
        return;
      }
      $formState->setValue('source_entity', $sourceEntity);
    }
    else {
      throw new \RuntimeException("The view bulk operation has been triggered from an invalid page.");
    }
  }

  /**
   * Order the nodes, so that the custom page parent nodes are first.
   *
   * This is important in order to check if the parent of the 2nd tier custom
   * pages were moved too. While we are iterating over all nodes, we are also
   * collecting the UUIDs of 1st tier custom page OG menu links so that we can
   * check later if each of the 2nd tier custom pages are linked to one of these
   * parents. In this iteration we are also caching the relation between the
   * custom page nodes and their corresponding OG menu link.
   *
   * @param \Drupal\node\NodeInterface[] $nodes
   *   Nodes to be re-ordered.
   */
  protected function reorder(array &$nodes): void {
    $reordered = $others = [];
    while ($nodes) {
      $node = array_shift($nodes);
      $nid = $node->id();
      if ($node->bundle() === 'custom_page') {
        /** @var \Drupal\menu_link_content\MenuLinkContentInterface $menu_link */
        if ($menu_link = $this->getOgMenuLink($node)) {
          if (!$menu_link->getParentId()) {
            // It's a top custom page.
            $this->topLevelCustomPages[] = $menu_link->uuid();
            $reordered[$nid] = $node;
          }
          $this->pageMenuLinks[$nid] = $menu_link;
        }
      }
      if (!isset($reordered[$nid])) {
        $others[$nid] = $node;
      }
    }
    // Re-order the list.
    $nodes = array_merge($reordered, $others);
  }

  /**
   * Gets the OG menu link given a custom page.
   *
   * @param \Drupal\node\NodeInterface $customPage
   *   The custom page node.
   *
   * @return \Drupal\menu_link_content\MenuLinkContentInterface|null
   *   The OG menu link.
   */
  protected function getOgMenuLink(NodeInterface $customPage): ?MenuLinkContentInterface {
    $menuInstance = $this->getOgMenuInstance();
    if (empty($menuInstance)) {
      return NULL;
    }
    $menuLinkStorage = $this->entityTypeManager->getStorage('menu_link_content');
    $uri = 'entity:node/' . $customPage->id();
    $mids = $menuLinkStorage->getQuery()->accessCheck(FALSE)
      ->condition('bundle', 'menu_link_content')
      ->condition('menu_name', "ogmenu-{$menuInstance->id()}")
      ->condition('link.uri', $uri)
      ->execute();
    if ($mids) {
      $mid = reset($mids);
      return $menuLinkStorage->load($mid);
    }
    return NULL;
  }

  /**
   * Gets the OG menu instance corresponding to the actual source entity.
   *
   * @return \Drupal\og_menu\OgMenuInstanceInterface|null
   *   The OG menu instance.
   */
  protected function getOgMenuInstance(): ?OgMenuInstanceInterface {
    if (!isset($this->ogMenuInstance)) {
      $ogMenuStorage = $this->entityTypeManager->getStorage('ogmenu_instance');
      $ogMenuInstances = $ogMenuStorage->loadByProperties([
        'type' => 'navigation',
        'og_audience' => $this->configuration['source_entity']->id(),
      ]);
      $this->ogMenuInstance = $ogMenuInstances ? reset($ogMenuInstances) : NULL;
    }
    return $this->ogMenuInstance;
  }

}
