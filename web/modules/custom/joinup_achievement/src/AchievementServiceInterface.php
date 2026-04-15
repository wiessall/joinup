<?php

declare(strict_types=1);

namespace Drupal\joinup_achievement;

use Drupal\user\UserInterface;

/**
 * Interface for the achievement service.
 *
 * Provides methods for awarding and revoking flag-backed achievements, and for
 * checking whether a user currently holds a given achievement.
 */
interface AchievementServiceInterface {

  /**
   * Awards an achievement to a user by flagging the user entity.
   *
   * If the user already holds the achievement, this is a no-op.
   *
   * @param \Drupal\joinup_achievement\AchievementFlagInterface $achievement
   *   The achievement to award.
   * @param \Drupal\user\UserInterface $user
   *   The user to award the achievement to.
   */
  public function award(AchievementFlagInterface $achievement, UserInterface $user): void;

  /**
   * Revokes an achievement from a user by unflagging the user entity.
   *
   * If the user does not hold the achievement, this is a no-op.
   *
   * @param \Drupal\joinup_achievement\AchievementFlagInterface $achievement
   *   The achievement to revoke.
   * @param \Drupal\user\UserInterface $user
   *   The user to revoke the achievement from.
   */
  public function revoke(AchievementFlagInterface $achievement, UserInterface $user): void;

  /**
   * Checks whether a user currently holds a given achievement.
   *
   * @param \Drupal\joinup_achievement\AchievementFlagInterface $achievement
   *   The achievement to check.
   * @param \Drupal\user\UserInterface $user
   *   The user to check.
   *
   * @return bool
   *   TRUE if the user holds the achievement, FALSE otherwise.
   */
  public function hasAchievement(AchievementFlagInterface $achievement, UserInterface $user): bool;

}
