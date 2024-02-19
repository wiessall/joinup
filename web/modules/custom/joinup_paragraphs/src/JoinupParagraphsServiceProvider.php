<?php

declare(strict_types=1);

namespace Drupal\joinup_paragraphs;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceModifierInterface;
use Drupal\joinup_paragraphs\Access\LayoutParagraphsBuilderAccessOverrider;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Overwrites services.
 */
class JoinupParagraphsServiceProvider implements ServiceModifierInterface {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container): void {
    // Overrides layout_paragraphs.builder_access service.
    if ($container->hasDefinition('layout_paragraphs.builder_access')) {
      $definition = $container->getDefinition('layout_paragraphs.builder_access');
      $definition->setClass(LayoutParagraphsBuilderAccessOverrider::class)
        ->addArgument(new Reference('og.access'));
    }
  }

}
