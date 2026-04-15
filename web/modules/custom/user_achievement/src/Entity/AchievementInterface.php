<?php

declare(strict_types=1);

namespace Drupal\user_achievement\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface for Achievement entities.
 */
interface AchievementInterface extends ContentEntityInterface, EntityOwnerInterface {

}
