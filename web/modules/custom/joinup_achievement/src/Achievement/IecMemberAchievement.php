<?php

declare(strict_types=1);

namespace Drupal\joinup_achievement\Achievement;

use Drupal\joinup_achievement\AchievementFlagInterface;

/**
 * Achievement for members of the Interoperable Europe Community.
 */
final class IecMemberAchievement implements AchievementFlagInterface {

  /**
   * {@inheritdoc}
   */
  public function getFlagId(): string {
    return 'iec_member';
  }

  /**
   * {@inheritdoc}
   */
  public function getLabel(): string {
    return 'Interoperable Europe Community member';
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription(): string {
    return 'This user is a member of the Interoperable Europe Community';
  }

  /**
   * {@inheritdoc}
   */
  public function getIcon(): string {
    return 'check-circle';
  }

}
