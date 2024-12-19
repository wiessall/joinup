<?php

declare(strict_types=1);

namespace Drupal\joinup_user\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a "UserHeaderBlock" block.
 *
 * @Block(
 *   id = "user_header_block",
 *   admin_label = @Translation("User header"),
 *   context_definitions = {
 *     "user_route" = @ContextDefinition("entity:user", label = @Translation("User from URL"))
 *   }
 * )
 */
class UserHeaderBlock extends BlockBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    readonly string $plugin_id,
    readonly mixed $plugin_definition,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    /** @var \Drupal\user\Entity\User $user */
    $user = $this->getContext('user_route')->getContextValue();
    $view_builder = $this->entityTypeManager->getViewBuilder($user->getEntityTypeId());
    $build['user'] = $view_builder->view($user, 'group_header');

    return $build;
  }

}
