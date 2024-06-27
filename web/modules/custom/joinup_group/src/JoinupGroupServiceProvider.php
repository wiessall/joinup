<?php

declare(strict_types=1);

namespace Drupal\joinup_group;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceModifierInterface;

/**
 * Sets group_membership logging channel.
 */
class JoinupGroupServiceProvider implements ServiceModifierInterface {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container): void {
    if (!$container->hasParameter('monolog.channel_handlers')) {
      return;
    }

    $monologChannelHandlers = $container->getParameter('monolog.channel_handlers');
    $monologChannelHandlers['group_membership'] = $monologChannelHandlers['default'];
    $monologChannelHandlers['group_membership'][] = 'business_handler';

    $container->setParameter('monolog.channel_handlers', $monologChannelHandlers);
  }

}
