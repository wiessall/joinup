<?php

declare(strict_types = 1);

namespace Drupal\contact_information\EventSubscriber;

use Drupal\contact_information\Controller\ContactInformationContactController;
use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Swaps the email_contact.form route controller to provide more features.
 */
class ContactInformationContactFormRouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    if ($route = $collection->get('email_contact.form')) {
      $route->setDefault('_controller', ContactInformationContactController::class . '::content');
    }
  }

}
