<?php

declare(strict_types=1);

namespace Drupal\joinup_group\Entity;

/**
 * Reusable code for group menu link entities.
 */
trait GroupMenuLinkTrait {

  /**
   * A flag for whether this entity is wrapped in a plugin instance.
   */
  protected bool $insidePlugin = FALSE;

  /**
   * {@inheritdoc}
   */
  public function setInsidePlugin(): static {
    assert($this instanceof GroupMenuLinkInterface);
    $this->insidePlugin = TRUE;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function isInsidePlugin(): bool {
    assert($this instanceof GroupMenuLinkInterface);
    return $this->insidePlugin;
  }

}
