<?php

declare(strict_types=1);

namespace Drupal\joinup_group\Plugin\Menu;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin deriver for group menu link plugins.
 */
class GroupMenuLinkDeriver extends DeriverBase implements ContainerDeriverInterface {

  public function __construct(protected readonly Connection $db) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id): static {
    return new static($container->get('database'));
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition): array {
    // @todo Add derivative definitions for non-custom_page links.
    return $this->derivatives;
  }

}
