<?php

declare(strict_types=1);

namespace Drupal\joinup_achievement\Entity;

use Drupal\flag\Entity\Flagging;
use Drupal\joinup_achievement\AchievementFlagInterface;
use Drupal\joinup_achievement\AchievementServiceInterface;

/**
 * Bundle class for Flagging entities that represent achievement badges.
 *
 * Registered for the iec_member and portal_contributor flag bundles via
 * hook_entity_bundle_info_alter(). Reads badge metadata directly from
 * AchievementServiceInterface::ACHIEVEMENTS — no service injection required.
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
    return AchievementServiceInterface::ACHIEVEMENTS[$this->bundle()]['description'] ?? '';
  }

  /**
   * {@inheritdoc}
   */
  public function getIcon(): string {
    return AchievementServiceInterface::ACHIEVEMENTS[$this->bundle()]['icon'] ?? '';
  }

}
