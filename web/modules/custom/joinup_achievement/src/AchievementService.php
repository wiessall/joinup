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
  public function award(string $flagId, UserInterface $user): void {
    $flag = $this->flagService->getFlagById($flagId);
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
  public function revoke(string $flag_id, UserInterface $user): void {
    $flag = $this->flagService->getFlagById($flag_id);
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
  public function hasAchievement(string $flag_id, UserInterface $user): bool {
    $flag = $this->flagService->getFlagById($flag_id);
    if ($flag === NULL) {
      return FALSE;
    }

    return (bool) $flag->isFlagged($user);
  }

}
