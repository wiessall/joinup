<?php

declare(strict_types=1);

namespace Drupal\joinup_achievement;

/**
 * Defines the interface for flag-backed achievement classes.
 *
 * Each implementation represents a specific flag that is used to mark a user
 * as having earned a particular achievement. The flag ID returned by
 * getFlagId() must correspond to a flag.flag.* config entity whose entity_type
 * is 'user'.
 */
interface AchievementFlagInterface {

  /**
   * Returns the machine name of the flag entity backing this achievement.
   *
   * @return string
   *   A flag ID, e.g. 'iec_member'.
   */
  public function getFlagId(): string;

  /**
   * Returns the human-readable label for this achievement.
   *
   * @return string
   *   The achievement label shown in the UI.
   */
  public function getLabel(): string;

  /**
   * Returns the tooltip description shown on the user profile.
   *
   * @return string
   *   A short explanatory string describing what the achievement represents.
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
