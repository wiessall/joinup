<?php

declare(strict_types=1);

namespace Drupal\joinup_rdf\Routing;

use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\joinup_rdf\Controller\RdfExportController;
use Drupal\rdf_sync\RdfSyncMapper;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Subscriber for RDF export routes.
 */
class RdfExportRouteSubscriber extends RouteSubscriberBase {

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected EntityTypeBundleInfoInterface $entityTypeBundleInfo,
    protected RdfSyncMapper $rdfSyncMapper) {
  }

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection): void {
    foreach ($this->entityTypeManager->getDefinitions() as $entity_type_id => $entity_type) {
      $route = $this->getRdfExportRoute($entity_type);
      if ($route) {
        $collection->add("entity.$entity_type_id.joinup_rdf_export", $route);
      }
    }
  }

  /**
   * Gets the RDF export route.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type.
   *
   * @return \Symfony\Component\Routing\Route|null
   *   The generated route, if available.
   */
  protected function getRdfExportRoute(EntityTypeInterface $entity_type): ?Route {
    $canonical = $entity_type->getLinkTemplate('canonical');
    if (!$canonical) {
      return NULL;
    }

    $entity_type_id = $entity_type->id();
    $bundles = $this->entityTypeBundleInfo->getBundleInfo($entity_type_id);
    $bundles = array_filter(array_keys($bundles), function (string $bundle) use ($entity_type_id): bool {
      return $this->rdfSyncMapper->isMappedBundle($entity_type_id, $bundle);
    });
    if (!$bundles) {
      return NULL;
    }

    $route = new Route($canonical . '/rdf-export');
    $route
      ->addDefaults([
        '_controller' => RdfExportController::class . '::downloadLinks',
        '_title_callback' => RdfExportController::class . '::title',
      ])
      ->setRequirement('_entity_access', "{$entity_type_id}.view")
      ->setOption('parameters', [
        $entity_type_id => [
          'type' => 'entity:' . $entity_type_id,
          'bundle' => $bundles,
        ],
      ]);

    return $route;
  }

}
