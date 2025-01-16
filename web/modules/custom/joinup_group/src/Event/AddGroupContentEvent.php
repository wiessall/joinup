<?php

declare(strict_types=1);

namespace Drupal\joinup_group\Event;

use Drupal\joinup_group\Entity\GroupInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Provides an event class to react when building the group add content block.
 */
class AddGroupContentEvent extends Event {

  /**
   * A list of render arrays, each representing a menu item.
   *
   * @var array[]
   */
  protected $items = [];

  public function __construct(
    protected readonly GroupInterface $group,
  ) {}

  /**
   * Returns the group.
   *
   * @return \Drupal\joinup_group\Entity\GroupInterface
   *   The group entity.
   */
  public function getGroup(): GroupInterface {
    return $this->group;
  }

  /**
   * Returns the list if items.
   *
   * @return array[]
   *   A list of render arrays.
   */
  public function getItems(): array {
    return $this->items;
  }

  /**
   * Adds a new item to the block menu.
   *
   * @param array $item
   *   A render array.
   *
   * @return $this
   */
  public function addItem(array $item): self {
    $this->items[] = $item;
    return $this;
  }

}
