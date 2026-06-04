<?php

declare(strict_types=1);

namespace Drupal\joinup_group_content_management\Plugin\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\Entity\EntityReferenceSelection\SelectionPluginManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Menu\MenuLinkManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\collection\Entity\CollectionInterface;
use Drupal\custom_page\Entity\CustomPageInterface;
use Drupal\joinup_distribution\Entity\DistributionInterface;
use Drupal\joinup_group\Entity\GroupInterface;
use Drupal\joinup_group\Entity\GroupMenuLinkInterface;
use Drupal\joinup_group\Plugin\Menu\GroupMenuLink;
use Drupal\joinup_release\Entity\ReleaseInterface;
use Drupal\node\NodeInterface;
use Drupal\node\NodeStorageInterface;
use Drupal\views_bulk_operations\Action\ViewsBulkOperationsActionBase;

/**
 * Provides a VBO action that changes the group for nodes.
 *
 * This will also move the group menu links into the new group OG menus.
 */
#[Action(
  id: "joinup_change_group",
  label: new TranslatableMarkup("Move to other group"),
  type: "node",
)]
class ChangeGroupAction extends ViewsBulkOperationsActionBase implements ContainerFactoryPluginInterface {

  /**
   * List of nodes to be processed.
   *
   * @var array<int|string, \Drupal\node\NodeInterface>
   */
  protected array $nodes;

  /**
   * List of child entities keyed by their parent ID.
   *
   * @var array<int|string, array<int|string, \Drupal\node\NodeInterface>>
   */
  protected array $children;

  /**
   * Messages collected during the execution.
   *
   * @var array{error: list<string>, warning: list<string>, status: list<string>}
   */
  protected array $message;

  /**
   * Node storage static cache.
   */
  protected NodeStorageInterface $nodeStorage;

  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    protected readonly SelectionPluginManagerInterface $selectionPluginManager,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly MenuLinkManagerInterface $menuLinkManager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
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
  public function executeMultiple(array $objects): array {
    $this->nodes = $objects;

    // Reset internals to avoid stale data from a hypothetical previous run.
    $this->children = [];
    $this->message = ['error' => [], 'warning' => [], 'status' => []];

    $groupId = $this->getConfiguration()['group'];
    if (!($group = $this->getNodeStorage()->load($groupId)) || !$group instanceof GroupInterface) {
      throw new \RuntimeException("Cannot load group entity with ID $groupId");
    }

    $count = count($this->nodes);
    $this->prepareList($group);

    foreach ($this->nodes as $nid => $node) {
      if ($node->get('og_audience')->target_id == $groupId) {
        // Avoid reassigning the group again.
        continue;
      }

      $args = [
        '@title' => $node->label(),
        ':url' => $node->toUrl()->toString(),
        '@type' => $node->type->entity->label(),
        '@group' => $group->label(),
        ':group_url' => $group->toUrl()->toString(),
      ];

      try {
        // Prevent notification dispatching.
        // @see joinup_notification_dispatch_notification()
        $node->skip_notification = TRUE;
        $node->set('og_audience', $groupId);

        $message = [
          [
            '#markup' => $this->t('@type <a href=":url">@title</a> group was changed to <a href=":group_url">@group</a>.', $args),
          ],
        ];

        // List also the children of the node, if any.
        if (!empty($this->children[$nid])) {
          $message[] = [
            '#theme' => 'item_list',
            '#items' => [],
          ];

          foreach ($this->children[$nid] ?? [] as $child) {
            $child->skip_notification = TRUE;
            $child->set('og_audience', $groupId)->save();
            $args['@child_type'] = $child->type->entity->label();
            $args['@child_title'] = $child->label();
            $message[1]['#items'][] = $this->t('Child @child_type <a href=":url">@child_title</a> group was changed too.', $args);
          }
        }

        $node->save();
        $this->message['status'][] = $message;
      }
      catch (\Exception) {
        $this->message['error'][] = $this->t("Error while trying to change the group for '@title'.", $args);
      }
    }

    foreach (array_filter($this->message) as $type => $messages) {
      foreach ($messages as $message) {
        $this->messenger()->addMessage($message, $type);
      }
    }

    return [
      'message' => $this->t('Action processing results: @operation (@count).', [
        '@operation' => $this->getPluginDefinition()['label'],
        '@count' => $count,
      ]),
    ];
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
  public function buildConfigurationForm(array $form): array {
    /** @var \Drupal\Core\Entity\EntityReferenceSelection\SelectionPluginBase $selection */
    $selection = $this->selectionPluginManager->createInstance('default:node', [
      'target_type' => 'node',
      'target_bundles' => ['collection', 'solution'],
    ]);
    $selectionSettings = $selection->getConfiguration() + [
      'match_operator' => 'CONTAINS',
    ];

    $form['group'] = [
      '#title' => $this->t('Select the destination collection or solution'),
      '#type' => 'entity_autocomplete',
      '#target_type' => 'node',
      '#selection_handler' => 'default:node',
      '#selection_settings' => $selectionSettings,
      '#maxlength' => 2048,
      '#required' => TRUE,
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state): void {
    /** @var \Drupal\Core\Url $url */
    $url = $form_state->get('views_bulk_operations')['redirect_url'];
    if ($url->isRouted() && ($parameters = $url->getRouteParameters()) && !empty($parameters['node'])) {
      $sourceEntity = $this->getNodeStorage()->load($parameters['node']);
      if ($sourceEntity->id() === $form_state->getValue('group')) {
        $form_state->setErrorByName('group', $this->t("The destination group is the same as the source group: %group. Please, select other destination group.", [
          '%group' => $sourceEntity->label(),
        ]));
        return;
      }
      $form_state->setValue('source_entity', $sourceEntity);
    }
    else {
      throw new \RuntimeException("The view bulk operation has been triggered from an invalid page.");
    }
  }

  /**
   * Prepares the list of nodes to be processed.
   *
   * @param \Drupal\joinup_group\Entity\GroupInterface $group
   *   The destination group.
   */
  protected function prepareList(GroupInterface $group): void {
    $args = [
      '@group' => $group->label(),
      ':group_url' => $group->toUrl()->toString(),
    ];

    foreach ($this->nodes as $nid => $node) {
      $args = [
        '@title' => $node->label(),
        ':url' => $node->toUrl()->toString(),
        '@type' => $node->get('type')->entity->label(),
      ] + $args;

      if ($node instanceof CustomPageInterface && $node->isGroupCodeOfConductPage()) {
        $this->message['error'][] = [
          [
            '#markup' => $this->t('@type <a href=":url">@title</a> was not moved because it is set as the code of conduct.', $args),
          ],
        ];
        unset($this->nodes[$nid]);
      }
      elseif ($node instanceof GroupMenuLinkInterface) {
        foreach ($this->getGroupMenuLinkEntityChildren($node) as $child) {
          $this->children[$nid][$child->id()] = $child;
        }
        $this->resolveParent($node);
      }
      elseif ($node instanceof ReleaseInterface) {
        if ($group instanceof CollectionInterface) {
          $this->message['error'][] = [
            [
              '#markup' => $this->t('@type <a href=":url">@title</a> was not moved as <a href=":group_url">@group</a> is a collection. Releases can only be moved to a different solution.', $args),
            ],
          ];
          unset($this->nodes[$nid]);
          continue;
        }
        $this->children[$nid] = $node->getDistributions();
      }
    }

    // Check for release distributions whose release was not selected.
    foreach ($this->nodes as $nid => $node) {
      if ($node instanceof DistributionInterface && $node->getParent() instanceof ReleaseInterface && !isset($this->children[$nid])) {
        $this->message['error'][] = [
          [
            '#markup' => $this->t('@type <a href=":url">@title</a> was not moved in <a href=":group_url">@group</a> as it is not a standalone distribution. Please, move the parent release instead.', $args),
          ],
        ];
        unset($this->nodes[$nid]);
      }
    }
  }

  /**
   * Recomputes parent menu links, if case.
   *
   * If the entity has a parent not selected by the user, the node link will be
   * moved one level up, by assigning the parent of the parent as parent, until
   * it reaches top-level or a node that is part of the user selection.
   *
   * @param \Drupal\joinup_group\Entity\GroupMenuLinkInterface $node
   *   The node.
   */
  protected function resolveParent(GroupMenuLinkInterface $node): void {
    $menuSettings = $node->get('menu_settings')->value;
    foreach ($node->getGroup()->getGroupOgMenuInstances() as $ogMenuInstance) {
      $ogMenuType = $ogMenuInstance->bundle();
      if (!$menuSettings[$ogMenuType]['parent']) {
        // It's a top-level menu item. Nothing to do.
        continue;
      }

      $ancestors = array_keys($this->menuLinkManager->getParentIds($menuSettings[$ogMenuType]['parent']));

      // The first parent is the current node.
      array_shift($ancestors);
      // Add the parent of the top-level menu item.
      $ancestors[] = '';

      while ($ancestors) {
        $parentPluginId = array_shift($ancestors);

        if (!$parentNid = GroupMenuLink::extractNodeId($parentPluginId)) {
          // Parent is not a group menu link entity, such as a custom page; we
          // move up in the hierarchy.
          continue;
        }

        if (isset($this->nodes[$parentNid])) {
          // The user selected this ancestor parent to be moved. This is the new
          // parent of $node in the context of the current OG menu instance.
          break;
        }

        // The parent is the root ('').
      }

      $menuSettings[$ogMenuType]['parent'] = $parentPluginId;
    }

    $node->set('menu_settings', ['value' => $menuSettings]);
  }

  /**
   * Returns the list of child group menu links entities of the given entity.
   *
   * @param \Drupal\joinup_group\Entity\GroupMenuLinkInterface $entity
   *   The entity for which to get the children.
   *
   * @return array<int|string, \Drupal\joinup_group\Entity\GroupMenuLinkInterface>
   *   List of child entities.
   */
  protected function getGroupMenuLinkEntityChildren(GroupMenuLinkInterface $entity): array {
    $nids = [];
    foreach ($entity->getGroup()->getGroupOgMenuInstances() as $ogMenuInstance) {
      $ids = $this->menuLinkManager->getChildIds(GroupMenuLink::buildPluginId('node', $entity->id(), $ogMenuInstance->bundle()));
      foreach ($ids as $id) {
        if ($nid = GroupMenuLink::extractNodeId($id)) {
          $nids[] = $nid;
        }
      }
    }

    if (!$nids) {
      return [];
    }

    return $this->getNodeStorage()->loadMultiple(array_unique($nids));
  }

  /**
   * Returns the node storage.
   *
   * @return \Drupal\node\NodeStorageInterface
   *   The node storage.
   */
  protected function getNodeStorage(): NodeStorageInterface {
    if (!isset($this->nodeStorage)) {
      $this->nodeStorage = $this->entityTypeManager->getStorage('node');
    }
    return $this->nodeStorage;
  }

}
