<?php

declare(strict_types=1);

namespace Drupal\assessments;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface defining a binding requirement entity type.
 */
interface BindingRequirementInterface extends ContentEntityInterface, EntityOwnerInterface, EntityChangedInterface {

}
