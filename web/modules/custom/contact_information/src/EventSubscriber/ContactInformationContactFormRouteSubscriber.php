<?php

declare(strict_types = 1);

namespace Drupal\contact_information\EventSubscriber;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Sets as default controller ContactInformationContactController.
 */
class ContactInformationContactFormRouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    if ($route = $collection->get('email_contact.form')) {
      $route->setDefault('_controller', '\Drupal\contact_information\Controller\ContactInformationContactController::content');
    }
  }

}
