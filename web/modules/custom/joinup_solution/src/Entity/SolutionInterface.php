<?php

declare(strict_types=1);

namespace Drupal\joinup_solution\Entity;

use Drupal\assessments\Entity\AssessmentResourceInterface;
use Drupal\collection\Entity\CollectionContentInterface;
use Drupal\joinup_distribution\Entity\DistributionsParentInterface;
use Drupal\joinup_group\Entity\GroupInterface;
use Drupal\joinup_group\Entity\PinnableGroupContentInterface;
use Drupal\joinup_group\Entity\ShareableContentInterface;
use Drupal\joinup_paragraphs\ParagraphResourcesInterface;
use Drupal\joinup_release\Entity\ReleaseInterface;

/**
 * Interface for solution entities in Joinup.
 */
interface SolutionInterface extends GroupInterface, CollectionContentInterface, PinnableGroupContentInterface, DistributionsParentInterface, ShareableContentInterface, ParagraphResourcesInterface, AssessmentResourceInterface {

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
