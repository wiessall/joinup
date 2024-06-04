<?php

declare(strict_types=1);

namespace Drupal\joinup_distribution\Entity;

use Drupal\collection\Entity\CollectionContentInterface;
use Drupal\joinup_bundle_class\DescriptionInterface;
use Drupal\joinup_stats\Entity\DownloadCountAwareInterface;
use Drupal\joinup_workflow\ArchivableEntityInterface;
use Drupal\joinup_workflow\EntityWorkflowStateInterface;
use Drupal\node\NodeInterface;
use Drupal\solution\Entity\SolutionContentInterface;

/**
 * Interface for distributions.
 */
interface DistributionInterface extends NodeInterface, CollectionContentInterface, SolutionContentInterface, DownloadCountAwareInterface, DescriptionInterface, EntityWorkflowStateInterface, ArchivableEntityInterface {

  /**
   * Return the distribution's parent, either a release or a solution.
   *
   * @return \Drupal\joinup_distribution\Entity\DistributionsParentInterface
   *   The parent entity, either a release or a solution.
   *
   * @throws \Drupal\joinup_distribution\Exception\MissingDistributionParentException
   *   If the parent entity reference is missing or refers to an invalid entity.
   *   This should not occur during normal operation and calling code does not
   *   need to catch this exception. This will only be thrown in case something
   *   is seriously wrong, e.g. if the database is down.
   */
  public function getParent(): DistributionsParentInterface;

  /**
   * Checks whether the distribution parent is a solution rather than a release.
   *
   * @return bool
   *   Whether the distribution parent is a solution rather than a release.
   */
  public function isStandalone(): bool;

}
