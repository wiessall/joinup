<?php

declare(strict_types = 1);

namespace Drupal\joinup_group_content_management\Plugin\OgGroupResolver;

use Drupal\og\Plugin\OgGroupResolver\RouteGroupResolver;

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

  /**
   * {@inheritdoc}
   */
  protected function getContentEntityPaths(): array {
    return [
      '/rdf_entity/{rdf_entity}/manage-content' => 'rdf_entity',
    ];
  }

}
