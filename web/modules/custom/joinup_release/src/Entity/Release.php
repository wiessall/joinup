<?php

declare(strict_types=1);

namespace Drupal\joinup_release\Entity;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\joinup_bundle_class\DescriptionTrait;
use Drupal\joinup_bundle_class\JoinupBundleClassFieldAccessTrait;
use Drupal\joinup_distribution\Entity\DistributionParentTrait;
use Drupal\joinup_group\Entity\GroupContentTrait;
use Drupal\joinup_group\Exception\MissingGroupException;
use Drupal\joinup_publication_date\Entity\EntityPublicationTimeFallbackTrait;
use Drupal\joinup_rdf\Entity\RdfSyncEntityTrait;
use Drupal\joinup_solution\Entity\SolutionContentTrait;
use Drupal\joinup_solution\Entity\SolutionInterface;
use Drupal\joinup_solution\Exception\MissingSolutionException;
use Drupal\joinup_workflow\ArchivableEntityTrait;
use Drupal\joinup_workflow\EntityWorkflowStateTrait;
use Drupal\node\Entity\Node;
use Drupal\user\EntityOwnerTrait;

/**
 * Bundle class for the 'release' bundle.
 */
class Release extends Node implements ReleaseInterface {

  use ArchivableEntityTrait;
  use DescriptionTrait;
  use DistributionParentTrait;
  use EntityPublicationTimeFallbackTrait;
  use EntityWorkflowStateTrait;
  use GroupContentTrait;
  use JoinupBundleClassFieldAccessTrait;
  use RdfSyncEntityTrait;
  use SolutionContentTrait;
  use EntityOwnerTrait;
  use EntityPublicationTimeFallbackTrait;

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    if (!$this->isNew()) {
      return;
    }
    // Set the solution data if a solution is passed as a group, or the group
    // if the solution is passed as the solution data.
    if ($this->get('solution_data')->isEmpty() && !$this->get('og_audience')->isEmpty()) {
      $this->set('solution_data', $this->get('og_audience')->entity);
    }
    elseif ($this->get('og_audience')->isEmpty() && !$this->get('solution_data')->isEmpty()) {
      $this->set('og_audience', $this->get('solution_data')->entity);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getGroup(): SolutionInterface {
    $group = $this->getFirstReferencedEntity('og_audience');
    if (empty($group) || !$group instanceof SolutionInterface) {
      throw new MissingGroupException();
    }
    return $group;
  }

  /**
   * Returns the group reference field name.
   *
   * @return string
   *   The group reference field name.
   */
  protected function getGroupFieldName(): string {
    return 'og_audience';
  }

  /**
   * {@inheritdoc}
   */
  public function getGroupId(): string {
    $ids = $this->getReferencedEntityIds('og_audience');
    if (empty($ids['node'])) {
      throw new MissingGroupException();
    }
    return array_shift($ids['node']);
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
  public function isLatestRelease(): bool {
    return $this->id() === $this->getSolution()->getLatestReleaseId();
  }

  /**
   * {@inheritdoc}
   */
  public function getVersion(): ?string {
    /** @var \Drupal\Core\Field\FieldItemListInterface $field */
    $field = $this->get('release_number');
    if ($field->isEmpty() || !($field->value ?? FALSE)) {
      // @todo Replace the deprecation error with an exception in ISAICP-6217.
      // @see https://citnet.tech.ec.europa.eu/CITnet/jira/browse/ISAICP-6217
      @trigger_error('A release without version number is deprecated. The version number is required in ISAICP-6217.', E_USER_DEPRECATED);
      return '';
    }
    return $field->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getSolution(): SolutionInterface {
    // For releases, this method returns the solution revision at the time the
    // release has been created.
    $solution = $this->get('solution_data')->entity;
    if ($solution instanceof SolutionInterface) {
      return $solution;
    }
    throw new MissingSolutionException();
  }

}
