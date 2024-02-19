<?php

declare(strict_types=1);

namespace Drupal\joinup_tour;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\RouteMatchInterface;

/**
 * Helper methods for dealing with tours on Joinup.
 */
class TourHelper implements TourHelperInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The current route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected RouteMatchInterface $currentRouteMatch;

  /**
   * Constructs a TourHelper object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Routing\RouteMatchInterface $current_route_match
   *   The current route match.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, RouteMatchInterface $current_route_match) {
    $this->entityTypeManager = $entity_type_manager;
    $this->currentRouteMatch = $current_route_match;
  }

  /**
   * {@inheritdoc}
   */
  public function currentPageHasTour(): bool {
    $route_name = $this->currentRouteMatch->getRouteName();
    $route_parameters = $this->currentRouteMatch->getRawParameters()->all();

    $tour_storage = $this->entityTypeManager->getStorage('tour');
    /** @var \Drupal\tour\TourInterface[] $tours */
    $tours = $tour_storage->loadByProperties(['status' => TRUE]);
    foreach ($tours as $tour) {
      // This tour is configured to show up on this route.
      if ($tour->hasMatchingRoute($route_name, $route_parameters)) {
        return TRUE;
      }
    }

    // No tour has been configured for this route.
    return FALSE;
  }

}
