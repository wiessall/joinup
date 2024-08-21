<?php

declare(strict_types=1);

namespace Drupal\eif\Plugin\OgGroupResolver;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\eif\EifInterface;
use Drupal\eif\Entity\EifRecommendationInterface;
use Drupal\joinup_solution\Entity\SolutionInterface;
use Drupal\og\GroupTypeManagerInterface;
use Drupal\og\OgResolvedGroupCollectionInterface;
use Drupal\og\Plugin\OgGroupResolver\RouteGroupResolver;
use Drupal\rdf_sync\RdfSyncMapper;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Resolves the group from the route.
 *
 * Use this to make the EIF Toolbox group available as a route context on the:
 * - Canonical page of the EIF recommendations taxonomy.
 * - Recommendations page.
 * - Solutions page.
 *
 * @OgGroupResolver(
 *   id = "eif_group",
 *   label = "Group entity for the EIF recommendations",
 *   description = @Translation("Sets the EIF Toolbox as a context for the EIF
 *   recommendations' canonical path.")
 * )
 */
class EifGroupResolver extends RouteGroupResolver {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    RouteMatchInterface $route_match,
    GroupTypeManagerInterface $group_type_manager,
    EntityTypeManagerInterface $entity_type_manager,
    protected readonly RdfSyncMapper $mapper,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $route_match, $group_type_manager, $entity_type_manager);
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
      $container->get('rdf_sync.mapper'),
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getContentEntityPaths(): array {
    return [
      '/node/{node}/eif-perspective' => 'node',
      '/node/{node}/solutions/{arg_1}' => 'node',
      '/taxonomy/term/{taxonomy_term}' => 'taxonomy_term',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function resolve(OgResolvedGroupCollectionInterface $collection): void {
    if ($entity = $this->getContentEntity()) {

      if (($entity instanceof SolutionInterface && $entity->getUri() === EifInterface::EIF_ID) || $entity instanceof EifRecommendationInterface) {
        if ($solution = $this->mapper->getEntityByUri(EifInterface::EIF_ID)) {
          $collection->addGroup($solution, ['route']);
          $this->stopPropagation();
        }
      }

      // The 'EIF perspective' page.
      if ($this->routeMatch->getRouteName() === 'entity.node.eif_perspective' && $entity instanceof SolutionInterface) {
        $collection->addGroup($entity, ['route']);
        $this->stopPropagation();
      }
    }
  }

}
