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

  /**
   * The UUID of the assessment collection.
   */
  public const string GROUP_UUID = 'c5e99eae-6d31-47a0-b914-a4b9f4ea82cf';

}
