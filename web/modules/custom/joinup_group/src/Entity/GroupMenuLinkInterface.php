<?php

declare(strict_types=1);

namespace Drupal\joinup_group\Entity;

/**
 * Interface for entities that are deriving group menu links.
 */
interface GroupMenuLinkInterface extends GroupContentInterface {

  /**
   * Flags this instance as being wrapped in a group menu link plugin.
   *
   * @return $this
   *
   * @see \Drupal\menu_link_content\Entity\MenuLinkContent::setInsidePlugin()
   */
  public function setInsidePlugin(): static;

  /**
   * Checks whether this instance is wrapped in a group menu link plugin.
   *
   * @return bool
   *   TRUE if wrapped in a plugin.
   */
  public function isInsidePlugin(): bool;

}
