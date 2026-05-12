<?php

declare(strict_types=1);

namespace Drupal\joinup_test;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceModifierInterface;

/**
 * Modifies the container for the joinup_test module.
 */
class JoinupTestServiceProvider implements ServiceModifierInterface {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container): void {
    // Disable the coretags queuer, we do not use varnish on dev envs.
    // Also prevents TagInvalidation objects from accumulating during Behat.
    if ($container->hasDefinition('purge_queuer_coretags.queuer')) {
      $container->removeDefinition('purge_queuer_coretags.queuer');
    }
  }

}
