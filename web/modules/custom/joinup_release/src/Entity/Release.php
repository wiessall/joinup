<?php

declare(strict_types=1);

namespace Drupal\joinup_release\Entity;

use Drupal\asset_release\Entity\ReleaseInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\joinup_bundle_class\DescriptionTrait;
use Drupal\joinup_bundle_class\JoinupBundleClassFieldAccessTrait;
use Drupal\joinup_bundle_class\LogoTrait;
use Drupal\joinup_group\Entity\GroupContentTrait;
use Drupal\joinup_group\Entity\GroupInterface;
use Drupal\joinup_group\Exception\MissingGroupException;
use Drupal\joinup_publication_date\Entity\EntityPublicationTimeFallbackTrait;
use Drupal\joinup_rdf\Entity\RdfSyncEntityTrait;
use Drupal\joinup_workflow\ArchivableEntityTrait;
use Drupal\joinup_workflow\EntityWorkflowStateTrait;
use Drupal\rdf_entity\Entity\Rdf;
use Drupal\solution\Entity\SolutionContentTrait;

/**
 * Bundle class for the 'release' bundle.
 */
class Release extends Rdf implements ReleaseInterface {

  use ArchivableEntityTrait;
  use DescriptionTrait;
  use EntityPublicationTimeFallbackTrait;
  use EntityWorkflowStateTrait;
  use GroupContentTrait;
  use JoinupBundleClassFieldAccessTrait;
  use LogoTrait;
  use RdfSyncEntityTrait;
  use SolutionContentTrait;

  /**
   * {@inheritdoc}
   */
  public function getGroup(): GroupInterface {
    $group = $this->getFirstReferencedEntity('is_version_of');
    if (empty($group) || !$group instanceof GroupInterface) {
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
    return 'is_version_of';
  }

  /**
   * {@inheritdoc}
   */
  public function getGroupId(): string {
    $ids = $this->getReferencedEntityIds('is_version_of');
    if (empty($ids['rdf_entity'])) {
      throw new MissingGroupException();
    }
    return array_shift($ids['rdf_entity']);
  }

  /**
   * {@inheritdoc}
   */
  public function getWorkflowStateFieldName(): string {
    return 'field_state';
  }

  /**
   * {@inheritdoc}
   */
  public function getLogoFieldName(): string {
    // @todo Override the
    //   \Drupal\joinup_bundle_class\LogoTrait::getLogoAsRenderArray
    //   in order to fetch the solution's logo *OR* create a computed field
    //   (preferred) and use it here.
    return 'field_is_logo';
  }

  /**
   * {@inheritdoc}
   */
  public function getDescriptionField(): string {
    return 'body';
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
  public function getDistributionIds(): array {
    return \Drupal::getContainer()->get('joinup_distribution.parent_distribution_resolver')->getParentsDistributionIds($this->id());
  }

}
