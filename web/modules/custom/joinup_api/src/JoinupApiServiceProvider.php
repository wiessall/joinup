<?php

declare(strict_types=1);

namespace Drupal\joinup_api;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;

/**
 * Modifies the notification sender service.
 */
class JoinupApiServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container): void {
    // Override the oauth_client.notification_sender service to use our
    // custom class.
    if ($container->hasDefinition('oauth_client.notification_sender')) {
      $definition = $container->getDefinition('oauth_client.notification_sender');
      $definition->setClass(NotificationSender::class);
      $definition->setAutowired(TRUE);
    }
  }

}
