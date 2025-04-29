<?php

declare(strict_types=1);

namespace Drupal\joinup_test;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderInterface;
use Drupal\joinup_test\EventSubscriber\SearchApiFinishRequestSubscriber;

/**
 * Registers services used in tests dynamically.
 */
class JoinupTestServiceProvider implements ServiceProviderInterface {

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container): void {
    if ($container->has('search_api.post_request_indexing')) {
      $container->register(SearchApiFinishRequestSubscriber::class)
        ->setAutowired(TRUE)
        ->setAutoconfigured(TRUE);
    }
  }

}
