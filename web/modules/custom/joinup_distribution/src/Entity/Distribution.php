<?php

declare(strict_types=1);

namespace Drupal\joinup_distribution\Entity;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\file_url\FileUrlHandler;
use Drupal\joinup_bundle_class\DescriptionTrait;
use Drupal\joinup_distribution\Exception\MissingDistributionParentException;
use Drupal\joinup_group\Entity\GroupContentTrait;
use Drupal\joinup_publication_date\Entity\EntityPublicationTimeFallbackTrait;
use Drupal\joinup_rdf\Entity\RdfSyncEntityTrait;
use Drupal\joinup_release\Entity\ReleaseInterface;
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
  use RdfSyncEntityTrait;
  use SolutionContentTrait;
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
  public function preSave(EntityStorageInterface $storage) {
    if (!empty($fileId = $this->access_url->target_id)) {
      if ($file = FileUrlHandler::urlToFile($fileId)) {
        if (!FileUrlHandler::isRemote($file)) {
          // Set the file size field.
          $this->get('file_size')->setValue($file->getSize());

          $tids = \Drupal::entityQuery('taxonomy_term')
            ->accessCheck(FALSE)
            ->condition('mime_type', $file->getMimeType())
            ->execute();
          if ($tids) {
            $this->set('format', reset($tids));
          }
        }
        else {
          // Trim the value so that leading and trailing spaces are removed.
          $this->set('access_url', trim($this->get('access_url')->target_id));
        }
      }
    }

    if ($this->isNew()) {
      // If the group is not assigned but the parent is, pass the group to the
      // distribution.
      if (!$this->get('release')->isEmpty()) {
        $release = $this->get('release')->entity;
        assert($release instanceof ReleaseInterface);
        $releaseParentSolution = $release->getGroup();
        if ($this->get(OgGroupAudienceHelperInterface::DEFAULT_FIELD)->isEmpty()) {
          $this->set(OgGroupAudienceHelperInterface::DEFAULT_FIELD, $releaseParentSolution);
        }
        elseif ($this->get(OgGroupAudienceHelperInterface::DEFAULT_FIELD)->target_id != $releaseParentSolution->id()) {
          throw new \LogicException('Cannot save distribution because its release belongs to a different solution than the solution assigned as OG audience');
        }
      }
    }

    parent::preSave($storage);
  }

  /**
   * {@inheritdoc}
   */
  public function postSave(EntityStorageInterface $storage, $update = TRUE): void {
    parent::postSave($storage, $update);

    // Re-index the parent of the distribution.
    // @todo Drop the try-catching on ISAICP-8572.
    try {
      if ($parent = $this->getParent()) {
        $this->reIndexDistributionParent($parent);
      }

      // Clear the cache of the solution of the distribution.
      if ($solution = $this->get(OgGroupAudienceHelperInterface::DEFAULT_FIELD)?->entity) {
        $this->clearSolutionCache($solution);
      }
    }
    catch (MissingDistributionParentException $e) {
      // The parent is missing, so we cannot re-index it.
    }
  }

  /**
   * {@inheritdoc}
   */
  public function delete() {
    $solution = $this->get(OgGroupAudienceHelperInterface::DEFAULT_FIELD)?->entity;
    try {
      $parent = $this->getParent();
    }
    catch (\Throwable) {
      // Nothing.
    }
    parent::delete();

    // Clear the cache of the solution of the distribution.
    if ($solution) {
      $this->clearSolutionCache($solution);
    }

    // Re-index the parent of the distribution.
    if (isset($parent)) {
      $this->reIndexDistributionParent($parent);
    }
  }

  /**
   * Clears the cache of the solution of the distribution.
   *
   * @param \Drupal\joinup_distribution\Entity\DistributionsParentInterface $solution
   *   The solution to clear the cache for.
   */
  protected function clearSolutionCache(DistributionsParentInterface $solution): void {
    /** @var \Drupal\Core\Cache\CacheTagsInvalidatorInterface $cacheTagsInvalidator */
    $cacheTagsInvalidator = \Drupal::service('cache_tags.invalidator');
    // Solutions show the licence of their releases in the solution header.
    // Clear the cache of the solution this release belongs to so any licence
    // change is picked up.
    $cacheTagsInvalidator->invalidateTags($solution->getCacheTagsToInvalidate());
  }

  /**
   * Re-indexes the parent of the distribution.
   *
   * @param \Drupal\joinup_distribution\Entity\DistributionsParentInterface $parent
   *   The parent to re-index.
   */
  protected function reIndexDistributionParent(DistributionsParentInterface $parent): void {
    /** @var \Drupal\search_api\Plugin\search_api\datasource\ContentEntityTrackingManager $contentTrackingManager */
    $contentTrackingManager = \Drupal::service('search_api.entity_datasource.tracking_manager');
    $contentTrackingManager->trackEntityChange($parent);
  }

  /**
   * {@inheritdoc}
   */
  public function getParent(): DistributionsParentInterface {
    $parent = $this->get('parent')->entity;
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
