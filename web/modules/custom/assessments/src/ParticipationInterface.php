<?php

declare(strict_types=1);

namespace Drupal\assessments;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface defining a participation entity type.
 */
interface ParticipationInterface extends ContentEntityInterface, EntityOwnerInterface, EntityChangedInterface {

}
