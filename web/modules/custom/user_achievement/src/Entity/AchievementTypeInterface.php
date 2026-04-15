<?php

declare(strict_types=1);

namespace Drupal\user_achievement\Entity;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Provides an interface for Achievement type config entities.
 */
interface AchievementTypeInterface extends ConfigEntityInterface {

  /**
   * Returns the tooltip description for this achievement type.
   */
  public function getDescription(): string;

  /**
   * Returns the icon ID for this achievement type (a Ventuno sprite icon name).
   */
  public function getIcon(): string;

  /**
   * Sets the icon ID for this achievement type.
   */
  public function setIcon(string $icon): static;

}
