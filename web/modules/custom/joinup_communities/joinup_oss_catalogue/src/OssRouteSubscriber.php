<?php

declare(strict_types=1);

namespace Drupal\joinup_oss_catalogue;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Alters existing routes related to OSS pages.
 */
class OssRouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  public function alterRoutes(RouteCollection $collection): void {
    // Disable access to the about page.
    if ($route = $collection->get('entity.rdf_entity.about_page')) {
      $route->setRequirement('_oss_access_check', 'TRUE');
    }

    // Remove user password reset page.
    if ($route = $collection->get('entity.rdf_entity.member_overview')) {
      $route->setRequirement('_oss_access_check', 'TRUE');
    }

  }

}
