<?php

declare(strict_types=1);

namespace Drupal\joinup_achievement\Achievement;

use Drupal\joinup_achievement\AchievementFlagInterface;

/**
 * Achievement for users who are recognised Portal contributors.
 */
final class PortalContributorAchievement implements AchievementFlagInterface {

  /**
   * {@inheritdoc}
   */
  public function getFlagId(): string {
    return 'portal_contributor';
  }

  /**
   * {@inheritdoc}
   */
  public function getLabel(): string {
    return 'Portal contributor';
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription(): string {
    return 'This user is a recognised contributor to the Interoperable Europe Portal';
  }

  /**
   * {@inheritdoc}
   */
  public function getIcon(): string {
    return 'award';
  }

}
