<?php

declare(strict_types=1);

namespace Drupal\joinup_badge\Entity;

use Drupal\flag\Entity\Flagging;

/**
 * Bundle class for flagging entities that represent badges.
 */
class BadgeAwarding extends Flagging implements BadgeAwardingInterface {

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
    return $this->getFlag()->getThirdPartySetting('joinup_badge', 'description');
  }

  /**
   * {@inheritdoc}
   */
  public function getIcon(): string {
    return $this->getFlag()->getThirdPartySetting('joinup_badge', 'icon');
  }

}
