<?php

declare(strict_types=1);

namespace Drupal\joinup_achievement;

use Drupal\flag\FlagServiceInterface;
use Drupal\user\UserInterface;

/**
 * Awards and revokes flag-backed achievements on user entities.
 */
class AchievementService implements AchievementServiceInterface {

  /**
   * Constructs an AchievementService object.
   */
  public function __construct(
    protected readonly FlagServiceInterface $flagService,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function award(AchievementFlagInterface $achievement, UserInterface $user): void {
    $flag = $this->flagService->getFlagById($achievement->getFlagId());
    if ($flag === NULL) {
      return;
    }

    if ($flag->isFlagged($user)) {
      return;
    }

    $this->flagService->flag($flag, $user);
  }

  /**
   * {@inheritdoc}
   */
  public function revoke(AchievementFlagInterface $achievement, UserInterface $user): void {
    $flag = $this->flagService->getFlagById($achievement->getFlagId());
    if ($flag === NULL) {
      return;
    }

    if (!$flag->isFlagged($user)) {
      return;
    }

    $this->flagService->unflag($flag, $user);
  }

  /**
   * {@inheritdoc}
   */
  public function hasAchievement(AchievementFlagInterface $achievement, UserInterface $user): bool {
    $flag = $this->flagService->getFlagById($achievement->getFlagId());
    if ($flag === NULL) {
      return FALSE;
    }

    return (bool) $flag->isFlagged($user);
  }

}
