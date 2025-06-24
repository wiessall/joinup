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
use Drupal\search_api\Query\ResultSetInterface;
use Drupal\search_api\SearchApiException;
use Drupal\state_machine_revisions\RevisionManagerInterface;
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

  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    protected readonly AccountInterface $currentUser,
    protected readonly RouteMatchInterface $currentRouteMatch,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly RevisionManagerInterface $revisionManager,
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
      $container->get('entity_type.manager'),
      $container->get('state_machine_revisions.revision_manager')
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
   *   Thrown if the Search API Index entity storage definition is invalid.
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   *   Thrown if the Search API Index entity storage definition is not found.
   * @throws \Drupal\search_api\SearchApiException
   *   Thrown if an error occurs during the execution of the search query.
   */
  protected function getRows(): array {
    $index = $this->entityTypeManager->getStorage('search_api_index')->load('unpublished');
    /** @var \Drupal\search_api\Query\QueryInterface $query */
    $query = $index->query();
    $query->addCondition('entity_author', [$this->currentUser->id()], 'IN');
    $results = $query->execute();
    $entities = $this->getResultEntities($results);
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
   * Returns the entities listed in the given query result set.
   *
   * @param \Drupal\search_api\Query\ResultSetInterface $result
   *   The query results object.
   *
   * @return \Drupal\Core\Entity\EntityInterface[]
   *   The render array for the search results.
   *
   * @throws \Exception
   *    Thrown if the item loaded is not a node or an rdf entity.
   */
  protected function getResultEntities(ResultSetInterface $result): array {
    $results = [];
    foreach ($result->getResultItems() as $item) {
      try {
        $entity = $item->getOriginalObject()->getValue();
        if ($entity instanceof NodeInterface) {
          $entity = $this->revisionManager->loadLatestRevision($entity);
        }
        else {
          throw new \Exception('Only nodes should be loaded.');
        }
      }
      catch (SearchApiException $e) {
        $entity = NULL;
      }
      // Search results might be stale, so we check if the entity has been
      // found in the system.
      if (!$entity) {
        continue;
      }
      if (!$entity->access('view')) {
        continue;
      }
      $results[] = $entity;
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
    $access = $user_route->id() === $this->currentUser->id() ? AccessResult::forbidden() : AccessResult::allowed();
    return $access->cachePerUser();
  }

}
