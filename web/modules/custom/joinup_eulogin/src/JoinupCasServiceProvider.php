<?php

declare(strict_types = 1);

namespace Drupal\joinup_eulogin;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceModifierInterface;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Modifies the 'cas.user_manager' service default implementation.
 *
 * @todo Remove CasUserManager service override ISAICP-7530
 * @see https://www.drupal.org/project/cas/issues/3038279
 */
class JoinupCasServiceProvider implements ServiceModifierInterface {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container): void {
    if ($container->hasDefinition('cas.user_manager')) {
      $container->getDefinition('cas.user_manager')
        ->setClass(CasUserManager::class)
        ->addArgument(new Reference('messenger'))
        ->addArgument(new Reference('state'));
    }
  }

}
