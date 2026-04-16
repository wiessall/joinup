<?php

declare(strict_types=1);

namespace Drupal\joinup_achievement;

use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\flag\FlagServiceInterface;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Awards and revokes flag-backed achievements on user entities.
 *
 * This class is the single source of truth for which flags are achievements
 * and for their display metadata (icon ID and description).
 */
class AchievementService {

  use AutowireTrait;

  /**
   * The UUID of the Interoperable Europe Community group.
   */
  public const IEC_COMMUNITY = 'ba527094-00bc-45de-98fb-c732c6c66fec';

  /**
   * Metadata for each achievement flag.
   *
   * Keys are flag machine names. Each entry contains:
   * - icon: A Ventuno sprite icon ID (see the theme's icons.svg).
   * - description: Short tooltip text shown on the user profile.
   */
  public const ACHIEVEMENTS = [
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
   * Constructs an AchievementService object.
   */
  public function __construct(
    #[Autowire(service: 'flag')]
    protected readonly FlagServiceInterface $flagService,
  ) {}

  /**
   * Returns the flag IDs that represent achievement badges.
   *
   * @return string[]
   *   Ordered list of flag machine names.
   */
  public function getAchievementFlagIds(): array {
    return array_keys(self::ACHIEVEMENTS);
  }

  /**
   * Returns the Ventuno sprite icon ID for a given achievement flag.
   *
   * @param string $flagId
   *   The achievement flag machine name.
   *
   * @return string
   *   An icon ID from the Ventuno theme sprite, or empty string if unknown.
   */
  public function getIcon(string $flagId): string {
    return self::ACHIEVEMENTS[$flagId]['icon'] ?? '';
  }

  /**
   * Returns the tooltip description for a given achievement flag.
   *
   * @param string $flagId
   *   The achievement flag machine name.
   *
   * @return string
   *   A short description of what the achievement represents, or empty string.
   */
  public function getDescription(string $flagId): string {
    return self::ACHIEVEMENTS[$flagId]['description'] ?? '';
  }

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
  public function award(string $flagId, UserInterface $user): void {
    $flag = $this->flagService->getFlagById($flagId);
    if ($flag === NULL || $flag->isFlagged($user)) {
      return;
    }
    $this->flagService->flag($flag, $user);
  }

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
  public function revoke(string $flagId, UserInterface $user): void {
    $flag = $this->flagService->getFlagById($flagId);
    if ($flag === NULL || !$flag->isFlagged($user)) {
      return;
    }
    $this->flagService->unflag($flag, $user);
  }

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
  public function hasAchievement(string $flagId, UserInterface $user): bool {
    $flag = $this->flagService->getFlagById($flagId);
    return $flag !== NULL && (bool) $flag->isFlagged($user);
  }

}
