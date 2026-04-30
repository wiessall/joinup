<?php

declare(strict_types=1);

namespace Drupal\joinup_badge;

use Drupal\flag\FlaggingInterface;

/**
 * Interface for flagging bundle classes that representing awarding badges.
 */
interface BadgeAwardingInterface extends FlaggingInterface {

  /**
   * Returns the human-readable label for this badge.
   *
   * @return string
   *   The label read from the backing flag entity.
   */
  public function getLabel(): string;

  /**
   * Returns the tooltip description shown on the user profile.
   *
   * @return string
   *   A short description of what the badge represents.
   */
  public function getDescription(): string;

  /**
   * Returns the Ventuno sprite icon ID for this badge.
   *
   * @return string
   *   An icon ID from the Ventuno theme sprite (e.g. 'award', 'check-circle').
   */
  public function getIcon(): string;

}
