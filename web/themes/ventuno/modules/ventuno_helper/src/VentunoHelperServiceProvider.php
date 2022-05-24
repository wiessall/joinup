<?php

declare(strict_types = 1);

namespace Drupal\ventuno_helper;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceModifierInterface;
use Drupal\ventuno_helper\Render\MainContent\VentunoModalRenderer;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Alters 'main_content_renderer.modal' service to use a custom class.
 *
 * @see \Drupal\ventuno_helper\Render\MainContent\VentunoModalRenderer
 */
class VentunoHelperServiceProvider implements ServiceModifierInterface {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    $container->getDefinition('main_content_renderer.modal')
      ->setClass(VentunoModalRenderer::class)
      ->addArgument(new Reference('main_content_renderer.bs4_modal'))
      ->addArgument(new Reference('theme.manager'));
  }

}
