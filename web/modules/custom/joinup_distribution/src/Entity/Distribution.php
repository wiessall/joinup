<?php

declare(strict_types=1);

namespace Drupal\joinup_distribution\Entity;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\joinup_bundle_class\DescriptionTrait;
use Drupal\joinup_distribution\Exception\MissingDistributionParentException;
use Drupal\joinup_group\Entity\GroupContentTrait;
use Drupal\joinup_stats\Entity\DownloadCountAwareInterface;
use Drupal\joinup_stats\Entity\DownloadCountAwareTrait;
use Drupal\joinup_workflow\ArchivableEntityTrait;
use Drupal\joinup_workflow\EntityWorkflowStateTrait;
use Drupal\node\Entity\Node;
use Drupal\og\OgGroupAudienceHelperInterface;
use Drupal\solution\Entity\SolutionContentTrait;
use Drupal\solution\Entity\SolutionInterface;

/**
 * Bundle class for distributions.
 */
class Distribution extends Node implements DistributionInterface {

  use ArchivableEntityTrait;
  use DescriptionTrait;
  use DownloadCountAwareTrait;
  use EntityWorkflowStateTrait;
  use GroupContentTrait;
  use SolutionContentTrait;

  /**
   * Fields populated with statistical information by the joinup_stats module.
   */
  const JOINUP_STATS_FIELDS = [
    DownloadCountAwareInterface::class => 'download_count',
  ];

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    if (!$this->isNew()) {
      return;
    }

    // If the group is not assigned but the parent is, pass the group to the
    // distribution.
    if (!$this->get(OgGroupAudienceHelperInterface::DEFAULT_FIELD)->target_id && $this->get('distribution_parent')->target_id) {
      if ($parent = $this->getParent()) {
        if ($parent instanceof SolutionInterface) {
          $this->set(OgGroupAudienceHelperInterface::DEFAULT_FIELD, $parent->id());
        }
        else {
          // The parent is a release, thus, get the solution instead.
          $this->set(OgGroupAudienceHelperInterface::DEFAULT_FIELD, $parent->getGroup()->id());
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getParent(): DistributionsParentInterface {
    $parent = $this->get('distribution_parent')?->entity;

    /** @var \Drupal\joinup_distribution\Entity\DistributionsParentInterface $parent */
    if (!$parent instanceof DistributionsParentInterface) {
      // During normal operation every distribution should have a parent entity,
      // so the only way a parent can be missing is because of an unexpected
      // condition occurring at runtime, for example if a data store goes
      // offline.
      throw new MissingDistributionParentException();
    }

    return $parent;
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
    return 'description';
  }

  /**
   * {@inheritdoc}
   */
  public function getWorkflowStateFieldName(): string {
    return 'field_state';
  }

}
