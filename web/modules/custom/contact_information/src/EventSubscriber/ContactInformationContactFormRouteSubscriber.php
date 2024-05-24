<?php

declare(strict_types=1);

namespace Drupal\contact_information\EventSubscriber;

use Drupal\Core\Routing\RouteSubscriberBase;
use Drupal\contact_information\Controller\ContactInformationContactFormController;
use Symfony\Component\Routing\RouteCollection;

/**
 * Swaps the email_contact.form route controller to provide more features.
 *
 * It overwrites controller in order to set custom form, otherwise we are not
 * able to add additional fields to the contact form.
 *
 * @see \Drupal\contact_information\Form\ContactInformationContactForm
 */
class ContactInformationContactFormRouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    if ($route = $collection->get('email_contact.form')) {
      $route->setDefault('_controller', ContactInformationContactFormController::class . '::content');
      $route->setRequirement('_custom_access', ContactInformationContactFormController::class . '::accessCheck');
    }
  }

}
