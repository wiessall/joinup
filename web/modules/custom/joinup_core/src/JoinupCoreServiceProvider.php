<?php

declare(strict_types = 1);

namespace Drupal\joinup_core;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderBase;
use Drupal\joinup_core\EventSubscriber\LayoutBuilderBuildSubscriber;
use Joinup\Traits\DigitQaPipelineAwareTrait;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Alters and registers services for Joinup Core.
 */
class JoinupCoreServiceProvider extends ServiceProviderBase {

  use DigitQaPipelineAwareTrait;

  /**
   * Sets Monolog handler paths from DRUPAL_MONOLOG_PATHS and adds dblog.
   *
   * @param ContainerBuilder $container
   *   The ContainerBuilder whose service definitions can be altered.
   */
  public function alter(ContainerBuilder $container): void {
    // Workaround FPFISSUPP-15044.
    // @see https://citnet.tech.ec.europa.eu/CITnet/jira/browse/FPFISSUPP-15044
    // @todo Remove once FPFISSUPP-15044 is fixed.
    $paths = !static::isDigitQaPipeline() ? getenv('DRUPAL_MONOLOG_PATHS') : 'default_handler:/tmp/drupal/default.log;php_handler:/tmp/drupal/php.log';

    // Sets the Monolog handler paths from DRUPAL_MONOLOG_PATHS env var.
    if ($paths) {
      foreach (explode(';', $paths) as $item) {
        $item = str_replace(' ', '', $item);
        if (strpos($item, ':') !== FALSE) {
          [$handler, $path] = explode(':', $item);
          if ($handler && $path) {
            $container->setParameter("joinup_core.monolog.paths.{$handler}", $path);
          }
        }
      }
    }

    // If dblog module is enabled, log also in the {watchdog} table. The dblog
    // module is not enabled on production for performance reasons but is useful
    // on local development and testing environments for debug purposes.
    if ($container->hasParameter('container.modules')) {
      if (array_key_exists('dblog', $container->getParameter('container.modules'))) {
        if ($container->hasParameter('monolog.channel_handlers')) {
          $monolog_channel_handlers = $container->getParameter('monolog.channel_handlers');
          $monolog_channel_handlers['default'][] = 'drupal.dblog';
          $monolog_channel_handlers['php'][] = 'drupal.dblog';
          $container->setParameter('monolog.channel_handlers', $monolog_channel_handlers);
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container): void {
    // Goal here is to prevent making Layout Builder a hard dependency.
    $modules = $container->getParameter('container.modules');
    if (!isset($modules['layout_builder'])) {
      return;
    }

    $container
      ->register(
      'joinup_core.layout_builder.view_subscriber',
      LayoutBuilderBuildSubscriber::class
      )
      ->addArgument(new Reference('current_user'))
      ->addTag('event_subscriber');
  }

}
