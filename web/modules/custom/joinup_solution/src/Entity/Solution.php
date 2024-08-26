<?php

declare(strict_types=1);

namespace Drupal\joinup_solution\Entity;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Field\EntityReferenceFieldItemList;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\collection\Entity\CollectionInterface;
use Drupal\contact_information\Traits\ContactInformationTrait;
use Drupal\joinup_bundle_class\AbstractTrait;
use Drupal\joinup_bundle_class\DescriptionTrait;
use Drupal\joinup_bundle_class\JoinupBundleClassFieldAccessTrait;
use Drupal\joinup_bundle_class\JoinupBundleClassMetaEntityTrait;
use Drupal\joinup_bundle_class\LogoTrait;
use Drupal\joinup_bundle_class\ShortIdTrait;
use Drupal\joinup_distribution\Entity\DistributionParentTrait;
use Drupal\joinup_featured\FeaturedContentTrait;
use Drupal\joinup_group\Entity\GroupContentTrait;
use Drupal\joinup_group\Entity\GroupInterface;
use Drupal\joinup_group\Entity\GroupTrait;
use Drupal\joinup_group\Entity\PinnableGroupContentTrait;
use Drupal\joinup_group\Exception\MissingGroupException;
use Drupal\joinup_publication_date\Entity\EntityPublicationTimeFallbackTrait;
use Drupal\joinup_rdf\Entity\RdfSyncEntityTrait;
use Drupal\joinup_release\Entity\ReleaseInterface;
use Drupal\joinup_workflow\ArchivableEntityTrait;
use Drupal\joinup_workflow\EntityWorkflowStateTrait;
use Drupal\node\Entity\Node;
use Drupal\og\OgGroupAudienceHelperInterface;
use Drupal\og\OgMembershipInterface;
use Drupal\topic\Entity\TopicReferencingEntityTrait;

/**
 * Entity subclass for the 'solution' bundle.
 */
class Solution extends Node implements SolutionInterface {

  use AbstractTrait;
  use ArchivableEntityTrait;
  use ContactInformationTrait;
  use DescriptionTrait;
  use DistributionParentTrait;
  use EntityPublicationTimeFallbackTrait;
  use EntityWorkflowStateTrait;
  use FeaturedContentTrait;
  use GroupContentTrait;
  use GroupTrait;
  use JoinupBundleClassFieldAccessTrait;
  use JoinupBundleClassMetaEntityTrait;
  use LogoTrait;
  use PinnableGroupContentTrait;
  use RdfSyncEntityTrait;
  use ShortIdTrait;
  use StringTranslationTrait;
  use TopicReferencingEntityTrait;

  /**
   * {@inheritdoc}
   */
  public function getCollection(): CollectionInterface {
    return $this->getGroup();
  }

  /**
   * {@inheritdoc}
   */
  public function getGroup(): GroupInterface {
    $field_item = $this->getFirstItem(OgGroupAudienceHelperInterface::DEFAULT_FIELD);
    if (!$field_item || $field_item->isEmpty()) {
      throw new MissingGroupException();
    }
    $collection = $field_item->entity;
    if (empty($collection)) {
      // The collection entity can be empty in case it has been deleted and the
      // affiliated solutions have not yet been garbage collected.
      throw new MissingGroupException();
    }
    return $collection;
  }

  /**
   * {@inheritdoc}
   */
  public function getGroupId(): string {
    $ids = $this->getReferencedEntityIds(OgGroupAudienceHelperInterface::DEFAULT_FIELD);
    if (empty($ids['node'])) {
      throw new MissingGroupException();
    }
    return array_shift($ids['node']);
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
  public function getReleaseIds(): array {
    return $this->entityTypeManager()->getStorage('node')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'release')
      ->condition(OgGroupAudienceHelper::DEFAULT_FIELD, $this->id())
      ->sort('created')
      ->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function getReleases(): array {
    return $this->entityTypeManager()->getStorage('node')
      ->loadMultiple($this->getReleaseIds());
  }

  /**
   * {@inheritdoc}
   */
  public function getLatestReleaseId(): ?string {
    return $this->get('latest_release')->isEmpty() ? NULL : (string) $this->get('latest_release')->target_id;
  }

  /**
   * {@inheritdoc}
   */
  public function getLatestRelease(): ?ReleaseInterface {
    return $this->get('latest_release')->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function getAffiliatedCollections(): array {
    $collections = [];
    foreach ($this->getReferencedEntities(OgGroupAudienceHelperInterface::DEFAULT_FIELD) as $collection) {
      $collections[$collection->id()] = $collection;
    }
    return $collections;
  }

  /**
   * {@inheritdoc}
   */
  public function getAffiliatedCollectionIds(): array {
    return $this->getReferencedEntityIds(OgGroupAudienceHelperInterface::DEFAULT_FIELD)['node'] ?? [];
  }

  /**
   * {@inheritdoc}
   */
  public function getPinnableGroups(): array {
    return $this->getAffiliatedCollections();
  }

  /**
   * {@inheritdoc}
   */
  public function getPinnableGroupIds(): array {
    return $this->getAffiliatedCollectionIds();
  }

  /**
   * {@inheritdoc}
   */
  public function getLogoFieldName(): string {
    return 'logo';
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
  public function getGroupModerationFieldName(): string {
    return 'group_moderation';
  }

  /**
   * {@inheritdoc}
   */
  public function getContentCreationFieldName(): string {
    return 'content_creation';
  }

  /**
   * {@inheritdoc}
   */
  public function doGetGroupContentIds(): array {
    $ids = ['node' => $this->getNodeGroupContent()];
    $releases = $this->getReleases();
    foreach ($releases as $release) {
      $ids = NestedArray::mergeDeep($ids, [
        'node' => [
          'distribution' => $release->getDistributionIds(),
        ],
      ]);
    }

    if (!empty($ids['node']['distribution'])) {
      // Depending on the code, sometimes the IDs will be string and sometimes
      // they will be integers. We need to make sure they are all integers.
      // For example, in
      // `\Drupal\joinup_group\Entity\GroupTrait::getNodeGroupContent`, the code
      // iterates over the IDs and re-assigns them to the array. This converts
      // the numeric strings into integers.
      $ids['node']['distribution'] = array_unique(array_map('intval', $ids['node']['distribution']));
    }
    return $ids;
  }

  /**
   * {@inheritdoc}
   */
  public function getNewMembershipSuccessMessage(OgMembershipInterface $membership): TranslatableMarkup {
    return $this->t('You have subscribed to this solution and will receive notifications for it. To manage your subscriptions go to <em>My subscriptions</em> in your user menu.');
  }

  /**
   * {@inheritdoc}
   */
  public function getExistingMembershipMessage(OgMembershipInterface $membership): TranslatableMarkup {
    $parameters = [
      '%group' => $this->label(),
    ];
    switch ($membership->getState()) {
      case OgMembershipInterface::STATE_BLOCKED:
        return $this->t('You cannot subscribe to %group because your account has been blocked.', $parameters);

      default:
        return $this->t('You are already subscribed to %group.', $parameters);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getSharedInField(): ?EntityReferenceFieldItemList {
    return $this->get('shared_in');
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTagsToInvalidate(): array {
    $tags = $this->id() ? ['solution_download_count:' . md5($this->id())] : [];
    return Cache::mergeTags(parent::getCacheTagsToInvalidate(), $tags);
  }

  /**
   * {@inheritdoc}
   */
  public function getContactInformationFieldName(): string {
    return 'contact';
  }

  /**
   * {@inheritdoc}
   */
  public function getWebsiteLinkFieldName(): string {
    return 'landing_page';
  }

  /**
   * {@inheritdoc}
   */
  public function getAbstractField(): string {
    return 'abstract';
  }

}
