<?php

declare(strict_types=1);

namespace Drupal\collection\Entity;

use Drupal\joinup_bundle_class\AbstractInterface;
use Drupal\joinup_bundle_class\DescriptionInterface;
use Drupal\joinup_featured\FeaturedContentInterface;
use Drupal\joinup_group\Entity\GroupInterface;
use Drupal\joinup_publication_date\Entity\EntityPublicationTimeInterface;
use Drupal\joinup_workflow\ArchivableEntityInterface;
use Drupal\joinup_workflow\EntityWorkflowStateInterface;
use Drupal\node\NodeInterface;

/**
 * Interface for collection entities in Joinup.
 */
interface CollectionInterface extends NodeInterface, EntityPublicationTimeInterface, EntityWorkflowStateInterface, GroupInterface, FeaturedContentInterface, ArchivableEntityInterface, DescriptionInterface, AbstractInterface {

  /**
   * Returns the solutions that are affiliated with this collection.
   *
   * @param bool $onlyPublished
   *   (optional) Whether to return only published solutions. Defaults to FALSE.
   *
   * @return \Drupal\joinup_solution\Entity\SolutionInterface[]
   *   The solutions.
   */
  public function getSolutions(bool $onlyPublished = FALSE): array;

  /**
   * Returns the IDs of the solutions that are affiliated with this collection.
   *
   * @param bool $onlyPublished
   *   (optional) Whether to return only published solutions. Defaults to FALSE.
   *
   * @return string[]
   *   The solutions.
   */
  public function getSolutionIds(bool $onlyPublished = FALSE): array;

  /**
   * Returns the collection glossary settings.
   *
   * @return array
   *   The collection glossary settings.
   *
   * @throws \Exception
   *   When the collection misses a 'collection_settings' meta entity.
   */
  public function getGlossarySettings(): array;

  /**
   * Whether the collection is closed.
   *
   * In a closed collection users can only become members after being approved
   * by a facilitator. The user memberships will be initially in pending state.
   *
   * @return bool
   *   TRUE if the collection is closed, FALSE if it is open.
   */
  public function isClosed(): bool;

}
