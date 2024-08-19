<?php

declare(strict_types=1);

namespace Drupal\joinup_group\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Alters existing routes for the Joinup user multiple cancel form.
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection): void {
    // Deny access to group admin pages.
    $routes = [
      'entity.node.og_admin_routes',
      'entity.node.og_admin_routes.members',
      'entity.node.og_admin_routes.add_membership_page',
      'entity.og_membership.og_admin_routes',
      'entity.og_membership.og_admin_routes.members',
      'entity.og_membership.og_admin_routes.add_membership_page',
      'entity.og_membership.add_form',
    ];
    foreach ($routes as $route_name) {
      if ($route = $collection->get($route_name)) {
        $route->setRequirement('_access', 'FALSE');
      }
    }

    // Override the confirmation form to delete multiple users with our version
    // that prevents deletion of users that are sole owners of collections.
    if ($route = $collection->get('user.multiple_cancel_confirm')) {
      $route->addDefaults([
        '_form' => '\Drupal\joinup_group\Form\UserMultipleCancelConfirm',
      ]);
    }

    // Fix the group route param for devel module.
    if ($route = $collection->get('entity.og_membership.devel_load_with_references')) {
      $options = $route->getOptions();
      $options['parameters']['group'] = ['type' => 'entity:rdf_entity'];
      $route->setOptions($options);
    }

    // Add the '_joinup_group' requirement to 'common' routes.
    $routes = [
      'view.group_content_management.manage',
      'view.group_content_management.csv_export',
      'view.moderated_content.moderated_content',
    ];
    foreach ($routes as $route_name) {
      if ($route = $collection->get($route_name)) {
        $route->setRequirement('_joinup_group', 'TRUE');
      }
    }
  }

}
