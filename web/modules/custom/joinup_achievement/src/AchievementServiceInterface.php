<?php

declare(strict_types=1);

namespace Drupal\joinup_achievement;

use Drupal\user\UserInterface;

/**
 * Interface for the achievement service.
 *
 * Acts as the single source of truth for which flags are considered
 * achievements and for each badge's display metadata (icon and description).
 */
interface AchievementServiceInterface {

  /**
   * The UUID of the Interoperable Europe Community group.
   */
  const IEC_COMMUNITY = 'ba527094-00bc-45de-98fb-c732c6c66fec';

  /**
   * Metadata for each achievement flag.
   *
   * Keys are flag machine names. Each entry contains:
   * - icon: A Ventuno sprite icon ID (see the theme's icons.svg).
   * - description: Short tooltip text shown on the user profile.
   */
  const ACHIEVEMENTS = [
    'iec_member' => [
      'icon' => 'patch-check',
      'description' => 'This user is a verified member of the Interoperable Europe Community.',
    ],
    'portal_contributor' => [
      'icon' => 'award',
      'description' => 'This user has actively contributed to the Interoperable Europe Portal.',
    ],
  ];

  /**
   * Returns the flag IDs that represent achievement badges.
   *
   * @return string[]
   *   Ordered list of flag machine names.
   */
  public function getAchievementFlagIds(): array;

  /**
   * Returns the Ventuno sprite icon ID for a given achievement flag.
   *
   * @param string $flagId
   *   The achievement flag machine name.
   *
   * @return string
   *   An icon ID from the Ventuno theme sprite (e.g. 'award'), or an empty
   *   string if the flag is unknown.
   */
  public function getIcon(string $flagId): string;

  /**
   * Returns the tooltip description for a given achievement flag.
   *
   * @param string $flagId
   *   The achievement flag machine name.
   *
   * @return string
   *   A short description of what the achievement represents, or an empty
   *   string if the flag is unknown.
   */
  public function getDescription(string $flagId): string;

  /**
   * Awards an achievement to a user by flagging the user entity.
   *
   * If the user already holds the achievement, this is a no-op.
   *
   * @param string $flagId
   *   The machine name of the achievement flag (e.g. 'iec_member').
   * @param \Drupal\user\UserInterface $user
   *   The user to award the achievement to.
   */
  public function award(string $flagId, UserInterface $user): void;

  /**
   * Revokes an achievement from a user by unflagging the user entity.
   *
   * If the user does not hold the achievement, this is a no-op.
   *
   * @param string $flagId
   *   The machine name of the achievement flag (e.g. 'iec_member').
   * @param \Drupal\user\UserInterface $user
   *   The user to revoke the achievement from.
   */
  public function revoke(string $flagId, UserInterface $user): void;

  /**
   * Checks whether a user currently holds a given achievement.
   *
   * @param string $flagId
   *   The machine name of the achievement flag (e.g. 'iec_member').
   * @param \Drupal\user\UserInterface $user
   *   The user to check.
   *
   * @return bool
   *   TRUE if the user holds the achievement, FALSE otherwise.
   */
  public function hasAchievement(string $flagId, UserInterface $user): bool;

}
