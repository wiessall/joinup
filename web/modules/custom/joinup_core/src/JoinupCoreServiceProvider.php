<?php

declare(strict_types=1);

namespace Drupal\joinup_core;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceModifierInterface;

/**
 * Sets Monolog handler paths from DRUPAL_MONOLOG_PATHS and adds dblog logging.
 */
class JoinupCoreServiceProvider implements ServiceModifierInterface {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container): void {
    $paths = getenv('DRUPAL_MONOLOG_PATHS') ?: 'default_handler:/tmp/drupal/log/default.log;php_handler:/tmp/drupal/log/php.log';

    // Sets the Monolog handler paths from DRUPAL_MONOLOG_PATHS env var.
    if ($paths) {
      foreach (explode(';', $paths) as $item) {
        $item = str_replace(' ', '', $item);
        if (str_contains($item, ':')) {
          [$handler, $path] = explode(':', $item);
          if ($handler && $path) {
            $container->setParameter("joinup_core.monolog.paths.$handler", $path);
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

}
