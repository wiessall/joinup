<?php

declare(strict_types=1);

namespace Drupal\joinup_badge;

use Drupal\user\UserInterface;

/**
 * Interface for the badge service.
 *
 * Acts as the single source of truth for which flags are considered
 * badges and for each badge's display metadata (icon and description).
 */
interface BadgeServiceInterface {

  /**
   * The UUID of the Interoperable Europe Community group.
   */
  const IEC_COMMUNITY = 'ba527094-00bc-45de-98fb-c732c6c66fec';

  /**
   * Metadata for each badge flag.
   *
   * Keys are flag machine names. Each entry contains:
   * - icon: A Ventuno sprite icon ID (see the theme's icons.svg).
   * - description: Short tooltip text shown on the user profile.
   */
  const BADGES = [
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
   * Returns the flag IDs that represent badges.
   *
   * @return string[]
   *   Ordered list of flag machine names.
   */
  public function getBadgeFlagIds(): array;

  /**
   * Returns the Ventuno sprite icon ID for a given badge flag.
   *
   * @param string $flagId
   *   The badge flag machine name.
   *
   * @return string
   *   An icon ID from the Ventuno theme sprite (e.g. 'award'), or an empty
   *   string if the flag is unknown.
   */
  public function getIcon(string $flagId): string;

  /**
   * Returns the tooltip description for a given badge flag.
   *
   * @param string $flagId
   *   The badge flag machine name.
   *
   * @return string
   *   A short description of what the badge represents, or an empty
   *   string if the flag is unknown.
   */
  public function getDescription(string $flagId): string;

  /**
   * Awards a badge to a user by flagging the user entity.
   *
   * If the user already holds the badge, this is a no-op.
   *
   * @param string $flagId
   *   The machine name of the badge flag (e.g. 'iec_member').
   * @param \Drupal\user\UserInterface $user
   *   The user to award the badge to.
   */
  public function award(string $flagId, UserInterface $user): void;

  /**
   * Revokes a badge from a user by unflagging the user entity.
   *
   * If the user does not hold the badge, this is a no-op.
   *
   * @param string $flagId
   *   The machine name of the badge flag (e.g. 'iec_member').
   * @param \Drupal\user\UserInterface $user
   *   The user to revoke the badge from.
   */
  public function revoke(string $flagId, UserInterface $user): void;

  /**
   * Checks whether a user currently holds a given badge.
   *
   * @param string $flagId
   *   The machine name of the badge flag (e.g. 'iec_member').
   * @param \Drupal\user\UserInterface $user
   *   The user to check.
   *
   * @return bool
   *   TRUE if the user holds the badge, FALSE otherwise.
   */
  public function hasBadge(string $flagId, UserInterface $user): bool;

}