<?php

declare(strict_types=1);

namespace Drupal\joinup_solution\Entity;

use Drupal\collection\Entity\CollectionContentInterface;
use Drupal\joinup_bundle_class\AbstractInterface;
use Drupal\joinup_bundle_class\DescriptionInterface;
use Drupal\joinup_distribution\Entity\DistributionsParentInterface;
use Drupal\joinup_featured\FeaturedContentInterface;
use Drupal\joinup_group\Entity\GroupInterface;
use Drupal\joinup_group\Entity\PinnableGroupContentInterface;
use Drupal\joinup_publication_date\Entity\EntityPublicationTimeInterface;
use Drupal\joinup_rdf\Entity\RdfSyncEntityInterface;
use Drupal\joinup_release\Entity\ReleaseInterface;
use Drupal\joinup_workflow\ArchivableEntityInterface;
use Drupal\joinup_workflow\EntityWorkflowStateInterface;
use Drupal\node\NodeInterface;

/**
 * Interface for solution entities in Joinup.
 */
interface SolutionInterface extends NodeInterface, CollectionContentInterface, EntityPublicationTimeInterface, EntityWorkflowStateInterface, FeaturedContentInterface, PinnableGroupContentInterface, GroupInterface, DistributionsParentInterface, ArchivableEntityInterface, DescriptionInterface, AbstractInterface, RdfSyncEntityInterface {

  /**
   * Returns the child releases.
   *
   * @return \Drupal\joinup_release\Entity\ReleaseInterface[]
   *   An array of child releases, keyed by their ID.
   */
  public function getReleases(): array;

  /**
   * Returns a list of child release IDs.
   *
   * @return string[]
   *   A list of release IDs.
   */
  public function getReleaseIds(): array;

  /**
   * Returns the latest release ID of this solution, if any.
   *
   * @return string|null
   *   The latest release ID of this solution, if any.
   */
  public function getLatestReleaseId(): ?string;

  /**
   * Returns the latest release of this solution, if any.
   *
   * @return \Drupal\joinup_release\Entity\ReleaseInterface|null
   *   The latest release of this solution, if any.
   */
  public function getLatestRelease(): ?ReleaseInterface;

  /**
   * Returns the collections this solution is affiliated with.
   *
   * @return \Drupal\collection\Entity\CollectionInterface[]
   *   The affiliated collections, keyed by collection ID.
   */
  public function getAffiliatedCollections(): array;

  /**
   * Returns the IDs of the collections this solution is affiliated with.
   *
   * @return string[]
   *   The affiliated collection IDs.
   */
  public function getAffiliatedCollectionIds(): array;

}
