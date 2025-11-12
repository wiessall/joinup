<?php

declare(strict_types=1);

namespace Drupal\assessments;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\joinup_rdf\Entity\RdfSyncEntityInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface defining an assessment entity type.
 */
interface AssessmentInterface extends ContentEntityInterface, EntityOwnerInterface, EntityChangedInterface, RdfSyncEntityInterface {

}
