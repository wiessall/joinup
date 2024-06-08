<?php

declare(strict_types=1);

namespace Drupal\asset_distribution\Entity;

use Drupal\joinup_bundle_class\DescriptionTrait;
use Drupal\joinup_bundle_class\JoinupBundleClassMetaEntityTrait;
use Drupal\joinup_group\Entity\GroupContentTrait;
use Drupal\joinup_publication_date\Entity\EntityPublicationTimeFallbackTrait;
use Drupal\joinup_stats\Entity\DownloadCountAwareInterface;
use Drupal\joinup_stats\Entity\DownloadCountAwareTrait;
use Drupal\joinup_workflow\ArchivableEntityTrait;
use Drupal\joinup_workflow\EntityWorkflowStateTrait;
use Drupal\rdf_entity\Entity\Rdf;
use Drupal\solution\Entity\Solution;
use Drupal\solution\Entity\SolutionContentTrait;
use Drupal\solution\Entity\SolutionInterface;

/**
 * Bundle class for the 'asset_distribution' bundle.
 *
 * @todo Once we are on PHP 7.3 we should no longer include
 *   JoinupBundleClassMetaEntityTrait.
 */
class AssetDistribution extends Rdf implements AssetDistributionInterface {

  use ArchivableEntityTrait;
  use DescriptionTrait;
  use DownloadCountAwareTrait;
  use EntityWorkflowStateTrait;
  use GroupContentTrait;
  use SolutionContentTrait;
  use JoinupBundleClassMetaEntityTrait;
  use EntityPublicationTimeFallbackTrait;

  /**
   * Fields populated with statistical information by the joinup_stats module.
   */
  const JOINUP_STATS_FIELDS = [
    DownloadCountAwareInterface::class => 'download_count',
  ];

  /**
   * {@inheritdoc}
   */
  public function getParent(): DistributionsParentInterface {
    return new Solution(['rid' => 'solution']);
    // @todo Get rid of the usages and remove this file.
  }

  /**
   * {@inheritdoc}
   */
  public function isStandalone(): bool {
    return $this->getParent() instanceof SolutionInterface;
  }

  /**
   * {@inheritdoc}
   */
  public function getDescriptionField(): string {
    return 'field_ad_description';
  }

  /**
   * {@inheritdoc}
   */
  public function getWorkflowStateFieldName(): string {
    return 'field_ad_state';
  }

}
