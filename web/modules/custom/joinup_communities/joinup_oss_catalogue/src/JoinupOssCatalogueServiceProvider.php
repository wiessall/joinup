<?php

declare(strict_types=1);

namespace Drupal\joinup_oss_catalogue;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceModifierInterface;

/**
 * Sets eu_oss_catalogue logging channel.
 */
class JoinupOssCatalogueServiceProvider implements ServiceModifierInterface {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container): void {
    if (!$container->hasParameter('monolog.channel_handlers')) {
      return;
    }

    $monologChannelHandlers = $container->getParameter('monolog.channel_handlers');
    $monologChannelHandlers['eu_oss_catalogue'] = $monologChannelHandlers['default'];
    $monologChannelHandlers['eu_oss_catalogue'][] = 'eu_oss_catalogue';

    $container->setParameter('monolog.channel_handlers', $monologChannelHandlers);
  }

}
