<?php

declare(strict_types=1);

namespace Drupal\joinup_tour;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\RouteMatchInterface;

/**
 * Helper methods for dealing with tours on Portal.
 */
class TourHelper implements TourHelperInterface {

  /**
   * Constructs a TourHelper object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Routing\RouteMatchInterface $currentRouteMatch
   *   The current route match.
   */
  public function __construct(protected EntityTypeManagerInterface $entityTypeManager, protected RouteMatchInterface $currentRouteMatch) {
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
