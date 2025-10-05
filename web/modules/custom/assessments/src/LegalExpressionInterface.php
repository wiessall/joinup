<?php

declare(strict_types=1);

namespace Drupal\assessments;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface defining a legal expression entity type.
 */
interface LegalExpressionInterface extends ContentEntityInterface, EntityOwnerInterface, EntityChangedInterface {

}
