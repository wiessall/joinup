<?php

declare(strict_types=1);

namespace Drupal\rdf_sync;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderInterface;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;

/**
 * Adds a compiler pass to dependency injection container.
 */
class RdfSyncServiceProvider implements ServiceProviderInterface {

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container): void {
    // Run this compiler before RegisterSerializationClassesCompilerPass so that
    // services created by this pass, and tagged as 'encoder', are picked up by
    // RegisterSerializationClassesCompilerPass.
    // @see \Drupal\serialization\RegisterSerializationClassesCompilerPass
    $container->addCompilerPass(new RdfSyncEncoderCompilerPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, 10);
  }

}
