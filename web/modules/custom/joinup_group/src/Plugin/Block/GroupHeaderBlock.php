<?php

declare(strict_types=1);

namespace Drupal\joinup_group\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\joinup_group\Traits\GroupHeaderAccessTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the block used as a header on collection and solution pages.
 *
 * This header contains the name of the group, as well as some statistics and
 * the join / leave buttons.
 */
#[Block(
  id: "group_header_block",
  admin_label: new TranslatableMarkup("Group header"),
  context_definitions: [
    'og' => new ContextDefinition(
      data_type: 'entity',
      label: new TranslatableMarkup("Organic group"),
    ),
  ]
)]
class GroupHeaderBlock extends BlockBase implements ContainerFactoryPluginInterface {

  use GroupHeaderAccessTrait;

  public function __construct(
    array $configuration,
    string $plugin_id,
    readonly array $plugin_definition,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly RouteMatchInterface $routeMatch,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('current_route_match'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    /** @var \Drupal\Core\Entity\EntityInterface $group */
    $group = $this->getContext('og')->getContextValue();
    $view_builder = $this->entityTypeManager->getViewBuilder($group->getEntityTypeId());

    // We render the related view mode only in this block. Additional modules,
    // like collection and solution, will add more elements directly.
    $build['group'] = $view_builder->view($group, 'group_header');

    return $build;
  }

}
