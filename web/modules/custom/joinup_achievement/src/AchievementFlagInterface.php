<?php

declare(strict_types=1);

namespace Drupal\joinup_achievement;

use Drupal\flag\FlaggingInterface;

/**
 * Interface for Flagging bundle classes that represent achievement badges.
 *
 * Flagging entities whose bundle (flag_id) maps to an achievement implement
 * this interface when the module registers the appropriate bundle class via
 * hook_entity_bundle_info_alter(). Code that needs to consume achievement
 * flaggings should type-hint against this interface.
 */
interface AchievementFlagInterface extends FlaggingInterface {

  /**
   * Returns the human-readable label for this achievement badge.
   *
   * @return string
   *   The label read from the backing flag entity.
   */
  public function getLabel(): string;

  /**
   * Returns the tooltip description shown on the user profile.
   *
   * @return string
   *   A short description of what the achievement represents.
   */
  public function getDescription(): string;

  /**
   * Returns the Ventuno sprite icon ID for this achievement badge.
   *
   * @return string
   *   An icon ID from the Ventuno theme sprite (e.g. 'award', 'check-circle').
   */
  public function getIcon(): string;

}
