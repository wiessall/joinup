<?php

declare(strict_types=1);

namespace Drupal\joinup_media\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Deny access to media creation form for regular users.
 *
 * Regular users should be able to create new media entities ONLY via the modal
 * dialog provided by the Media Library module and not by accessing `media/add`
 * or `media/add/{type}` routes. Only privileged roles, such as 'moderator', are
 * allowed to access the media overview page (`admin/content/media`) via the
 * `access media overview` permission. So, it's safe to append this route
 * requirement (`_permission`) to the existing one (`_entity_create_any_access`)
 * in order to deny the access to regular users.
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection): void {
    foreach (['entity.media.add_page', 'entity.media.add_form'] as $name) {
      if ($route = $collection->get($name)) {
        $route->addRequirements(['_permission' => 'access media overview']);
      }
    }
  }

}
