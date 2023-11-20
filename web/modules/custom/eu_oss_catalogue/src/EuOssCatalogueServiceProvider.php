<?php

declare(strict_types = 1);

namespace Drupal\eu_oss_catalogue;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderInterface;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Registers image cache warmer service if Imagecache External is present.
 */
class EuOssCatalogueServiceProvider implements ServiceProviderInterface {

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container): void {
    $modules = $container->getParameter('container.modules');
    if (!array_key_exists('imagecache_external', $modules)) {
      return;
    }

    $container
      ->register(
        'eu_oss_catalogue.oss_solution.import.imagecache_warmer',
        EuOssCatalogueImagecacheWarmer::class
      )
      ->addArgument(new Reference('queue'));
  }

}
