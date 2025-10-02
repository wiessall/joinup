<?php

declare(strict_types=1);

namespace Drupal\joinup_release\Entity;

use Drupal\collection\Entity\CollectionContentInterface;
use Drupal\joinup_bundle_class\DescriptionInterface;
use Drupal\joinup_core\Entity\PermalinkInterface;
use Drupal\joinup_distribution\Entity\DistributionsParentInterface;
use Drupal\joinup_paragraphs\ParagraphResourcesInterface;
use Drupal\joinup_publication_date\Entity\EntityPublicationTimeInterface;
use Drupal\joinup_rdf\Entity\RdfSyncEntityInterface;
use Drupal\joinup_solution\Entity\SolutionContentInterface;
use Drupal\joinup_solution\Entity\SolutionInterface;
use Drupal\joinup_workflow\ArchivableEntityInterface;
use Drupal\joinup_workflow\EntityWorkflowStateInterface;
use Drupal\node\NodeInterface;

/**
 * Interface for asset release entities in Joinup.
 */
interface ReleaseInterface extends NodeInterface, RdfSyncEntityInterface, CollectionContentInterface, SolutionContentInterface, EntityPublicationTimeInterface, EntityWorkflowStateInterface, DistributionsParentInterface, DescriptionInterface, ArchivableEntityInterface, ParagraphResourcesInterface, PermalinkInterface {

  /**
   * Checks whether this release is the latest release of the parent solution.
   *
   * @return bool
   *   If this release is the latest release in the parent solution.
   */
  public function isLatestRelease(): bool;

  /**
   * Returns the release version number.
   *
   * @return string|null
   *   The release version number.
   *
   * @todo The return value should be enforced to `string` in ISAICP-6217.
   * @see https://citnet.tech.ec.europa.eu/CITnet/jira/browse/ISAICP-6217
   */
  public function getVersion(): ?string;

  /**
   * Returns the solution to which this release belongs to.
   *
   * @return \Drupal\solution\Entity\SolutionInterface
   *   The parent solution.
   */
  public function getGroup(): SolutionInterface;

}
