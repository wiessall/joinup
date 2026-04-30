<?php

declare(strict_types=1);

namespace Drupal\joinup_badge\Entity;

use Drupal\flag\Entity\Flagging;
use Drupal\joinup_badge\BadgeFlagInterface;
use Drupal\joinup_badge\BadgeService;

/**
 * Bundle class for Flagging entities that represent badges.
 */
class BadgeFlagging extends Flagging implements BadgeFlagInterface {

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
    return BadgeService::BADGES[$this->bundle()]['description'] ?? '';
  }

  /**
   * {@inheritdoc}
   */
  public function getIcon(): string {
    return BadgeService::BADGES[$this->bundle()]['icon'] ?? '';
  }

}