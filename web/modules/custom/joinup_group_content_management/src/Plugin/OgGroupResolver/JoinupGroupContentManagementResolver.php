<?php

declare(strict_types=1);

namespace Drupal\joinup_group_content_management\Plugin\OgGroupResolver;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\og\GroupTypeManagerInterface;
use Drupal\og\OgResolvedGroupCollectionInterface;
use Drupal\og\Plugin\OgGroupResolver\RouteGroupResolver;
use Drupal\sparql_entity_storage\UriEncoder;
use Drupal\views_bulk_operations\Form\ViewsBulkOperationsFormTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Resolves the group from the route.
 *
 * Use this to make the group (collection or solution) available as a route
 * context on paths that are not defined as entity link templates.
 *
 * @OgGroupResolver(
 *   id = "joinup_group_content_management",
 *   label = "Group content management resolver",
 *   description = @Translation("Checks if the current route is an entity path
 *   that belongs to a group entity.")
 * )
 */
class JoinupGroupContentManagementResolver extends RouteGroupResolver {

  use ViewsBulkOperationsFormTrait;

  /**
   * Constructor for JoinupGroupContentManagementResolver plugin.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $pluginId
   *   The plugin_id for the plugin instance.
   * @param mixed $pluginDefinition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Routing\RouteMatchInterface $routeMatch
   *   The route match service.
   * @param \Drupal\og\GroupTypeManagerInterface $groupTypeManager
   *   The group type manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\TempStore\PrivateTempStoreFactory $tempStoreFactory
   *   User private temporary storage factory.
   * @param \Drupal\Core\Session\AccountInterface $currentUser
   *   The current user.
   */
  public function __construct(
    array $configuration,
    string $pluginId,
    mixed $pluginDefinition,
    RouteMatchInterface $routeMatch,
    GroupTypeManagerInterface $groupTypeManager,
    EntityTypeManagerInterface $entityTypeManager,
    protected PrivateTempStoreFactory $tempStoreFactory,
    protected AccountInterface $currentUser
  ) {
    parent::__construct($configuration, $pluginId, $pluginDefinition, $routeMatch, $groupTypeManager, $entityTypeManager);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('current_route_match'),
      $container->get('og.group_type_manager'),
      $container->get('entity_type.manager'),
      $container->get('tempstore.private'),
      $container->get('current_user'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function resolve(OgResolvedGroupCollectionInterface $collection): void {
    $entity = $this->getContentEntity();
    if ($entity && $this->groupTypeManager->isGroup($entity->getEntityTypeId(), $entity->bundle())) {
      $collection->addGroup($entity, ['route', 'user']);
      $this->stopPropagation();
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function getContentEntity(): ?ContentEntityInterface {
    $entity = parent::getContentEntity();
    if ($entity) {
      return $entity;
    }

    return $this->getGroupFromTempStore();
  }

  /**
   * Gets rdf entity from temp store for VBO route.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface|null
   *   Rdf entity.
   */
  protected function getGroupFromTempStore(): ?ContentEntityInterface {
    $vboRoutes = [
      'views_bulk_operations.execute_batch',
      'views_bulk_operations.update_selection',
      'views_bulk_operations.execute_configurable',
      'views_bulk_operations.confirm',
    ];
    if (!in_array($this->routeMatch->getRouteName(), $vboRoutes, TRUE) ||
      $this->routeMatch->getParameter('view_id') !== 'group_content_management') {
      return NULL;
    }

    $data = $this->getTempstoreData(
      $this->routeMatch->getParameter('view_id'),
      $this->routeMatch->getParameter('display_id')
    );
    if (empty($data) || !isset($data['arguments'][0])) {
      return NULL;
    }

    $rdfId = UriEncoder::decodeUrl($data['arguments'][0]);
    return $this->entityTypeManager->getStorage('rdf_entity')
      ->load($rdfId);
  }

  /**
   * {@inheritdoc}
   */
  protected function getContentEntityPaths(): array {
    return [
      '/rdf_entity/{rdf_entity}/manage-content' => 'rdf_entity',
      '/rdf_entity/{rdf_entity}/manage-content/csv' => 'rdf_entity',
    ];
  }

  /**
   * Returns the current user.
   *
   * @return \Drupal\Core\Session\AccountInterface
   *   The current user.
   */
  protected function currentUser(): AccountInterface {
    return $this->currentUser;
  }

}
