<?php

declare(strict_types=1);

namespace Drupal\joinup_private_message\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Swaps the private message routes.
 */
class PrivateMessageRouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection): void {

    // Alter the path of the private message page route.
    if ($route = $collection->get('private_message.private_message_page')) {
      $route->setPath('/user/messages');
    }

    // Alter the canonical route to return a 404 page instead of removing it.
    if ($route = $collection->get('entity.private_message_thread.canonical')) {
      $route->setDefault('_controller', '\Drupal\joinup_private_message\Controller\PrivateMessageNotFoundController::notFound');
    }

    // Alter the path of the delete form for a private message thread.
    if ($route = $collection->get('entity.private_message_thread.delete_form')) {
      $route->setPath('/user/messages/{private_message_thread}/delete');
    }

    // Alter the path of the clear personal history form
    // for a private message thread.
    if ($route = $collection->get('entity.private_message_thread.clear_personal_history_form')) {
      $route->setPath('/user/messages/{private_message_thread}/clear-personal-history');
    }

    // Alter the path for creating a private message.
    if ($route = $collection->get('private_message.private_message_create')) {
      $route->setPath('/user/messages/message/create');
    }

    // Remove the ban page route.
    $collection->remove('entity.private_message.canonical');

    // Alter the path for deleting a private message.
    if ($route = $collection->get('entity.private_message.delete')) {
      $route->setPath('/user/messages/message/{private_message}/delete');
    }

    // Remove the route for viewing a private message.
    $collection->remove('entity.private_message.canonical');

    // Remove the ban autocomplete route.
    $collection->remove('private_message.ban_autocomplete');

    // Remove the ban user form route.
    $collection->remove('private_message.ban_user_form');

    // Remove the unban user form route.
    $collection->remove('private_message.unban_user_form');
  }

}
