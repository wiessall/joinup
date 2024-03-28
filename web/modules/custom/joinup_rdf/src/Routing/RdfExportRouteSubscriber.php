<?php

declare(strict_types=1);

namespace Drupal\joinup_rdf\Routing;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\Core\Routing\RoutingEvents;
use Drupal\joinup_rdf\Controller\RdfExportController;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Subscriber for rdf export routes.
 */
class RdfExportRouteSubscriber extends RouteSubscriberBase {

  /**
   * The entity type manager service.
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Constructs a new RouteSubscriber object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
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
    $route = new Route($canonical . '/rdf-export');
    $route
      ->addDefaults([
        '_controller' => RdfExportController::class . '::downloadLinks',
        '_title_callback' => RdfExportController::class . '::title',
      ])
      ->addRequirements([
        '_custom_access' => RdfExportController::class . '::access',
      ])
      ->setOption('_joinup_rdf_export_entity_type', $entity_type_id)
      ->setOption('parameters', [
        $entity_type_id => [
          'type' => 'entity:' . $entity_type_id,
        ],
      ]);

    return $route;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events = parent::getSubscribedEvents();
    $events[RoutingEvents::ALTER] = ['onAlterRoutes', 100];
    return $events;
  }

}
