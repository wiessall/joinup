<?php

declare(strict_types=1);

namespace Drupal\joinup_user\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;
use Drupal\rdf_entity\RdfInterface;
use Drupal\search_api\Query\ResultSetInterface;
use Drupal\search_api\SearchApiException;
use Drupal\state_machine_revisions\RevisionManagerInterface;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a block with the unpublished community content owned by the user.
 *
 * @Block(
 *   id = "user_unpublished",
 *   admin_label = @Translation("Unpublished content of user"),
 *   context_definitions = {
 *     "user_route" = @ContextDefinition("entity:user", label = @Translation("User from URL"))
 *   }
 * )
 */
class UserUnpublishedBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * Constructs a new UserUnpublishedBlock object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $pluginId
   *   The plugin_id for the plugin instance.
   * @param mixed $pluginDefinition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Session\AccountInterface $currentUser
   *   The current user proxy.
   * @param \Drupal\Core\Routing\RouteMatchInterface $currentRouteMatch
   *   The current route match service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   * @param \Drupal\state_machine_revisions\RevisionManagerInterface $revisionManager
   *   The revision manager service.
   */
  public function __construct(
    array $configuration,
    $pluginId,
    $pluginDefinition,
    protected AccountInterface $currentUser,
    protected RouteMatchInterface $currentRouteMatch,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected RevisionManagerInterface $revisionManager,
  ) {
    parent::__construct($configuration, $pluginId, $pluginDefinition);
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
        elseif ($entity instanceof RdfInterface) {
          $entity_id = $item->getOriginalObject()->getValue()->id();
          $entity = $this->getDraftRdf($entity_id);
        }
        else {
          throw new \Exception('Only nodes and Rdf entities should be loaded.');
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
   * Loads the latest revision on an rdf entity.
   *
   * @param string $entityId
   *   The content id.
   *
   * @return \Drupal\rdf_entity\RdfInterface|null
   *   The loaded RDF entity.
   */
  protected function getDraftRdf(string $entityId): ?RdfInterface {
    /** @var \Drupal\sparql_entity_storage\SparqlEntityStorageInterface $sparqlStorage */
    $sparqlStorage = $this->entityTypeManager->getStorage('rdf_entity');
    /** @var \Drupal\rdf_entity\RdfInterface|null $entity */
    $entity = $sparqlStorage->load($entityId, ['draft']);
    return $entity;
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
    foreach (['node', 'rdf_entity'] as $type) {
      $entityType = $this->entityTypeManager->getStorage($type)->getEntityType();
      $cacheTags = Cache::mergeTags($cacheTags, $entityType->getListCacheTags());
    }
    return $cacheTags;
  }

  /**
   * {@inheritdoc}
   */
  protected function blockAccess(AccountInterface $account): AccessResultInterface {
    // Only allow access if the user is viewing their own profile.
    if ($user = $this->currentRouteMatch->getParameter('user')) {
      if ($user instanceof UserInterface && $user->id() === $this->currentUser->id()) {
        return parent::blockAccess($account);
      }
    }
    return AccessResult::forbidden();
  }

}
