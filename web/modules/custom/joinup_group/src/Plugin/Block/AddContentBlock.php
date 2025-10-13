<?php

declare(strict_types=1);

namespace Drupal\joinup_group\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\joinup_group\Entity\GroupInterface;
use Drupal\joinup_group\Event\AddGroupContentEvent;
use Drupal\joinup_group\Traits\GroupPluginCacheTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Provides an 'AddContentBlock' block.
 */
#[Block(
  id: "add_content_block",
  admin_label: new TranslatableMarkup("Add content"),
  context_definitions: [
    'og' => new ContextDefinition(
      data_type: 'entity',
      label: new TranslatableMarkup("Organic group"),
    ),
  ],
)]
class AddContentBlock extends BlockBase implements ContainerFactoryPluginInterface {

  use GroupPluginCacheTrait;

  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
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
