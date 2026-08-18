<?php

declare(strict_types=1);

namespace Drupal\joinup_user\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\Context\EntityContextDefinition;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\node\NodeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a block with the unpublished community content owned by the user.
 */
#[Block(
  id: "user_unpublished",
  admin_label: new TranslatableMarkup("Unpublished content of user"),
  context_definitions: [
    'user_route' => new EntityContextDefinition(
      data_type: 'entity:user',
      label: new TranslatableMarkup("User from URL"),
    ),
  ]
)]
class UserUnpublishedBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The node bundle IDs that are listed in the block.
   *
   * @var array
   */
  const LISTED_BUNDLES = [
    'collection',
    'custom_page',
    'discussion',
    'distribution',
    'document',
    'event',
    'glossary',
    'news',
    'release',
    'solution',
  ];

  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    protected readonly AccountInterface $currentUser,
    protected readonly RouteMatchInterface $currentRouteMatch,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
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
      $container->get('current_user'),
      $container->get('current_route_match'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $rows = $this->getRows();
    if (empty($rows)) {
      return [];
    }
    $build = [
      // The 'listing' child key is needed to avoid copying the #attributes to
      // the parent block.
      // @see \Drupal\block\BlockViewBuilder::preRender()
      '#extra_suggestion' => 'block__separated',
      'listing' => [
        '#type' => 'container',
        '#extra_suggestion' => 'container__grid',
      ],
    ];

    $build['listing'] += $rows;
    return $build;
  }

  /**
   * Receives the unpublished content rows for the current user.
   *
   * @return array
   *   An array of rows to render.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   *   Thrown if the node entity storage definition is invalid.
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   *   Thrown if the node entity storage definition is not found.
   */
  protected function getRows(): array {
    $entities = $this->getUnpublishedNodes();
    $rows = [];

    foreach ($entities as $weight => $entity) {
      $view = $this->entityTypeManager->getViewBuilder($entity->getEntityTypeId())->view($entity, 'view_mode_tile');
      $rows[$weight] = [
        '#type' => 'container',
        '#extra_suggestion' => 'container__grid_item',
        '#weight' => $weight,
        'entity' => $view,
      ];
    }
    return $rows;
  }

  /**
   * Returns the nodes of the user whose latest revision is unpublished.
   *
   * Content that has been published but has a newer, unpublished revision is
   * included as well, since the pending revision is what the user still has to
   * act upon.
   *
   * @return \Drupal\node\NodeInterface[]
   *   The unpublished node revisions the user has access to.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   *   Thrown if the node entity storage definition is invalid.
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   *   Thrown if the node entity storage definition is not found.
   */
  protected function getUnpublishedNodes(): array {
    $storage = $this->entityTypeManager->getStorage('node');
    $revision_ids = $storage->getQuery()
      // Access is checked below, on the loaded revision.
      ->accessCheck(FALSE)
      ->latestRevision()
      ->condition('type', self::LISTED_BUNDLES, 'IN')
      ->condition('uid', $this->currentUser->id())
      ->condition('status', NodeInterface::NOT_PUBLISHED)
      ->sort('created', 'DESC')
      ->execute();

    // ::loadMultipleRevisions() does not guarantee the order of the returned
    // revisions, so walk the sorted query results instead.
    $nodes = $storage->loadMultipleRevisions(array_keys($revision_ids));
    $results = [];
    foreach (array_keys($revision_ids) as $revision_id) {
      $node = $nodes[$revision_id] ?? NULL;
      if (!$node || !$node->access('view')) {
        continue;
      }
      $results[] = $node;
    }
    return $results;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts(): array {
    // The page should be dependent on the user's groups.
    return Cache::mergeContexts(parent::getCacheContexts(), ['og_role']);
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags(): array {
    $cacheTags = parent::getCacheTags();
    $entityType = $this->entityTypeManager->getStorage('node')->getEntityType();
    return Cache::mergeTags($cacheTags, $entityType->getListCacheTags());
  }

  /**
   * {@inheritdoc}
   */
  protected function blockAccess(AccountInterface $account): AccessResultInterface {
    // Only allow access if the user is viewing their own profile.
    /** @var \Drupal\user\UserInterface $user_route */
    $user_route = $this->getContextValue('user_route');
    $access = $user_route->id() === $this->currentUser->id() ? AccessResult::allowed() : AccessResult::forbidden();
    return $access->cachePerUser();
  }

}
