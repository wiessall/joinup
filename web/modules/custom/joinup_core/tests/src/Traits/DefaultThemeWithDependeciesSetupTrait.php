<?php

declare(strict_types=1);

namespace Drupal\Tests\joinup_core\Traits;

use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Makes functional test use Ventuno theme by default.
 */
trait DefaultThemeWithDependeciesSetupTrait {

  /**
   * Does nothing. Themes should be installed after modules are installed.
   */
  protected function installDefaultThemeFromClassProperty(ContainerInterface $container): void {
  }

  /**
   * {@inheritdoc}
   */
  protected function installModulesFromClassProperty(ContainerInterface $container) {
    parent::installModulesFromClassProperty($container);
    // Re-initialize theme list to make it have the up-to-date module list.
    $container->set('theme_installer', \Drupal::service('theme_installer'));
    parent::installDefaultThemeFromClassProperty($container);
  }

}
