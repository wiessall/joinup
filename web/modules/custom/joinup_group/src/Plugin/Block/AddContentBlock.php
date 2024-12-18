<?php

declare(strict_types=1);

namespace Drupal\joinup_group\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\joinup_core\Traits\GroupPluginCacheTrait;
use Drupal\joinup_group\Entity\GroupInterface;
use Drupal\joinup_group\Event\AddGroupContentEvent;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Provides an 'AddContentBlock' block.
 *
 * @Block(
 *   id = "add_content_block",
 *   admin_label = @Translation("Add content"),
 *   context_definitions = {
 *     "og" = @ContextDefinition("entity", label = @Translation("Organic
 *   group"))
 *   },
 * )
 */
class AddContentBlock extends BlockBase implements ContainerFactoryPluginInterface {

  use GroupPluginCacheTrait;

  public function __construct(
    array $configuration,
    readonly string $plugin_id,
    readonly mixed $plugin_definition,
    protected readonly EventDispatcherInterface $eventDispatcher,
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
      $container->get('event_dispatcher')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $group = $this->getContext('og')->getContextValue();
    assert($group instanceof GroupInterface);

    $event = new AddGroupContentEvent($group);
    $this->eventDispatcher->dispatch($event);
    $items = $event->getItems();

    return $items ? [$items] : [];
  }

}
