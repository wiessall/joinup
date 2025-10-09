<?php

declare(strict_types=1);

namespace Drupal\joinup_eulogin;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;

/**
 * Modifies the EU Login related services.
 */
class JoinupEuloginServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container): void {
    // Do not show the message after user registration.
    // @see \Drupal\oe_authentication\Event\MessengerEuLoginEventSubscriber::showUserMessage()
    $container->removeDefinition('oe_authentication.messenger.event_subscriber');
  }

}
