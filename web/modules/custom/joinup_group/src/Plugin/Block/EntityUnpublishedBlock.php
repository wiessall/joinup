<?php

declare(strict_types=1);

namespace Drupal\joinup_group\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\joinup_group\Entity\GroupInterface;
use Drupal\rdf_entity\RdfInterface;
use Drupal\search_api\Query\QueryInterface;
use Drupal\search_api\Query\ResultSetInterface;
use Drupal\search_api\SearchApiException;
use Drupal\state_machine_revisions\RevisionManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a block with the unpublished community content of the entity.
 *
 * @Block(
 *   id = "entity_unpublished",
 *   admin_label = @Translation("Unpublished content of group"),
 *   context_definitions = {
 *     "og" = @ContextDefinition("entity:rdf_entity", label = @Translation("Organic group"))
 *   }
 * )
 */
class EntityUnpublishedBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The community content bundle IDs.
   *
   * @var array
   */
  const LISTED_BUNDLES = [
    'asset_release',
    'custom_page',
    'discussion',
    'document',
    'event',
    'news',
  ];

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The revision manager service.
   *
   * @var \Drupal\state_machine_revisions\RevisionManagerInterface
   */
  protected RevisionManagerInterface $revisionManager;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected AccountInterface $currentUser;

  /**
   * Constructs a new RecommendedContentBlock object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param string $plugin_definition
   *   The og membership manager service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\state_machine_revisions\RevisionManagerInterface $revision_manager
   *   The revision manager service.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    EntityTypeManagerInterface $entity_type_manager,
    RevisionManagerInterface $revision_manager,
    AccountProxyInterface $current_user,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
    $this->revisionManager = $revision_manager;
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('state_machine_revisions.revision_manager'),
      $container->get('current_user')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'states_filter' => [],
      'negate_states_filter' => FALSE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form['states_filter'] = [
      '#type' => 'textarea',
      '#title' => $this->t('States to show'),
      '#description' => $this->t('Add one state per line. If empty, all states will be shown. <br />Please note, that entities without a state field will receive the "published" and "unpublished" state as a value.'),
      '#default_value' => $this->configuration['states_filter'] ? implode(PHP_EOL, $this->configuration['states_filter']) : '',
    ];

    $form['negate_states_filter'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Negate states filter'),
      '#description' => $this->t('If checked, the states filter will be negated.'),
      '#default_value' => $this->configuration['negate_states_filter'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $this->configuration['states_filter'] = array_filter(array_map('trim', explode(PHP_EOL, $form_state->getValue('states_filter'))));
    $this->configuration['negate_states_filter'] = $form_state->getValue('negate_states_filter');
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $group = $this->getContext('og')->getContextValue();
    if (empty($group)) {
      return [];
    }

    $rows = $this->getRows($group);
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
   * Receives the content of the groups the user is a member of.
   *
   * @param \Drupal\rdf_entity\RdfInterface $group
   *   The group entity.
   *
   * @return array
   *   An array of rows to render.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   *   Thrown when the Search API Index entity type definition is invalid.
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   *   Thrown when the Search API Index entity type is not defined.
   * @throws \Drupal\search_api\SearchApiException
   *   Thrown when a problem occurs during execution of the search query.
   */
  protected function getRows(RdfInterface $group): array {
    $query = $this->getQuery($group);
    $results = $query->execute();
    $entities = $this->getResultEntities($results);
    $rows = [];

    foreach ($entities as $weight => $entity) {
      $view = $this->entityTypeManager->getViewBuilder($entity->getEntityTypeId())->view($entity, 'view_mode_tile');
      $rows[$weight] = [
        '#type' => 'container',
        '#extra_suggestion' => 'container__grid_item',
        '#weight' => $weight,
        $weight => $view,
      ];
    }
    return $rows;
  }

  /**
   * Constructs the search_api query.
   *
   * @param \Drupal\joinup_group\Entity\GroupInterface $group
   *   The related group.
   */
  protected function getQuery(GroupInterface $group): QueryInterface {
    $index = $this->entityTypeManager->getStorage('search_api_index')->load('unpublished');
    /** @var \Drupal\search_api\Query\QueryInterface $query */
    $query = $index->query()
      ->addCondition('entity_bundle', self::LISTED_BUNDLES, 'IN')
      ->addCondition('entity_groups', $group->id())
      ->sort('entity_created', 'DESC');

    if (!empty($this->configuration['states_filter'])) {
      $states_filter = array_filter(array_map('trim', $this->configuration['states_filter']));
      $query->addCondition('state', $states_filter, $this->configuration['negate_states_filter'] ? 'NOT IN' : 'IN');
    }
    return $query;
  }

  /**
   * Builds a renderable array for the search results.
   *
   * @param \Drupal\search_api\Query\ResultSetInterface $result
   *   The query results object.
   *
   * @return array
   *   The render array for the search results.
   */
  protected function getResultEntities(ResultSetInterface $result): array {
    $results = [];
    /** @var \Drupal\search_api\Item\ItemInterface $item */
    foreach ($result->getResultItems() as $item) {
      try {
        $entity = $item->getOriginalObject()->getValue();
        // Revisions are not yet implemented for RDF entities, even though they
        // inherit RevisionableInterface through ContentEntityInterface. We need
        // to check the RdfInterface directly.
        // @todo Remove this workaround once we have revisions for RDF entities.
        // @see https://citnet.tech.ec.europa.eu/CITnet/jira/browse/ISAICP-6481
        if (!$entity instanceof RdfInterface) {
          $entity = $this->revisionManager->loadLatestRevision($item->getOriginalObject()->getValue());
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

      // @todo The revision access check is replaced with an event subscriber.
      //   Disable this call for now. If tests are green this workaround can be
      //   removed.
      // @see \Drupal\joinup_community_content\EventSubscriber\Eventsubscriber::checkEntityRevisionAccess()
      if (TRUE || $entity->isDefaultRevision()) {
        $view_access = $entity->access('view', $this->currentUser, TRUE);
      }
      else {
        $view_access = $this->revisionAccessCheck->checkOgAccess($entity, $this->currentUser, 'view');
      }

      if (!$view_access->isAllowed()) {
        continue;
      }
      $results[] = $entity;
    }
    return $results;
  }

  /**
   * {@inheritdoc}
   *
   * The page should be dependent on the user's groups.
   */
  public function getCacheContexts(): array {
    return Cache::mergeContexts(parent::getCacheContexts(), ['user', 'og_role']);
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags(): array {
    $node_type = $this->entityTypeManager->getStorage('node')->getEntityType();
    $rdf_type = $this->entityTypeManager->getStorage('rdf_entity')->getEntityType();
    $cache_tags = Cache::mergeTags(parent::getCacheTags(), $node_type->getListCacheTags());
    return Cache::mergeTags($cache_tags, $rdf_type->getListCacheTags());
  }

}
