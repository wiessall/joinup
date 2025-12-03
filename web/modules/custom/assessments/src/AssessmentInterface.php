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

  /**
   * The UUID of the assessment created solutions collector.
   */
  public const string SOLUTION_COLLECTOR = 'e0f3b87b-9de3-48d5-8fff-5ec88ccd1a71';

  /**
   * Returns whether the entity is published.
   *
   * @return bool
   *   TRUE if the entity is published, FALSE otherwise.
   */
  public function isPublished(): bool;

}
