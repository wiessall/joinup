<?php

declare(strict_types=1);

namespace Drupal\joinup_achievement\Entity;

use Drupal\flag\Entity\Flagging;
use Drupal\joinup_achievement\AchievementFlagInterface;
use Drupal\joinup_achievement\AchievementService;

/**
 * Bundle class for Flagging entities that represent achievement badges.
 */
class AchievementFlagging extends Flagging implements AchievementFlagInterface {

  /**
   * {@inheritdoc}
   */
  public function getLabel(): string {
    return $this->getFlag()->label();
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription(): string {
    return AchievementService::ACHIEVEMENTS[$this->bundle()]['description'] ?? '';
  }

  /**
   * {@inheritdoc}
   */
  public function getIcon(): string {
    return AchievementService::ACHIEVEMENTS[$this->bundle()]['icon'] ?? '';
  }

}
