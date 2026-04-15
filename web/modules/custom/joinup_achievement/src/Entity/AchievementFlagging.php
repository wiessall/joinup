<?php

declare(strict_types=1);

namespace Drupal\joinup_achievement\Entity;

use Drupal\flag\Entity\Flagging;
use Drupal\joinup_achievement\AchievementFlagInterface;

/**
 * Bundle class for Flagging entities that represent achievement badges.
 *
 * Registered for the iec_member and portal_contributor flag bundles via
 * hook_entity_bundle_info_alter(). Reads badge metadata directly from the
 * backing Flag config entity.
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
    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function getIcon(): string {
    return '';
  }

}
