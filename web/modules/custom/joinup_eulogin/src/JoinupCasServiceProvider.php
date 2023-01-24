<?php

declare(strict_types = 1);

namespace Drupal\joinup_eulogin;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;

/**
 * Modifies the 'cas.user_manager' service default implementation.
 */
class JoinupCasServiceProvider extends ServiceProviderBase {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container): void {
    if ($container->hasDefinition('cas.user_manager')) {
      $definition = $container->getDefinition('cas.user_manager');
      $definition->setClass('Drupal\joinup_eulogin\CasUserManager');
    }
  }

}
