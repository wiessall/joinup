<?php

declare(strict_types=1);

namespace Drupal\collection\Entity;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\contact_information\Traits\ContactInformationTrait;
use Drupal\joinup_bundle_class\AbstractTrait;
use Drupal\joinup_bundle_class\DescriptionTrait;
use Drupal\joinup_bundle_class\JoinupBundleClassFieldAccessTrait;
use Drupal\joinup_bundle_class\JoinupBundleClassMetaEntityTrait;
use Drupal\joinup_bundle_class\LogoTrait;
use Drupal\joinup_bundle_class\ShortIdTrait;
use Drupal\joinup_featured\FeaturedContentTrait;
use Drupal\joinup_group\Entity\GroupTrait;
use Drupal\joinup_publication_date\Entity\EntityPublicationTimeFallbackTrait;
use Drupal\joinup_rdf\Entity\RdfSyncEntityTrait;
use Drupal\joinup_workflow\ArchivableEntityTrait;
use Drupal\joinup_workflow\EntityWorkflowStateTrait;
use Drupal\meta_entity\Entity\MetaEntity;
use Drupal\node\Entity\Node;
use Drupal\og\OgGroupAudienceHelper;
use Drupal\og\OgMembershipInterface;
use Drupal\topic\Entity\TopicReferencingEntityTrait;

/**
 * Entity subclass for the 'collection' bundle.
 */
class Collection extends Node implements CollectionInterface {

  use AbstractTrait;
  use ArchivableEntityTrait;
  use EntityPublicationTimeFallbackTrait;
  use EntityWorkflowStateTrait;
  use FeaturedContentTrait;
  use GroupTrait {
    createMembership as protected createMembershipTrait;
  }
  use JoinupBundleClassFieldAccessTrait;
  use JoinupBundleClassMetaEntityTrait;
  use LogoTrait;
  use ShortIdTrait;
  use StringTranslationTrait;
  use TopicReferencingEntityTrait;
  use DescriptionTrait;
  use ContactInformationTrait;
  use RdfSyncEntityTrait;

  /**
   * {@inheritdoc}
   *
   * phpcs:disable Generic.CodeAnalysis.UselessOverridingMethod.Found
   */
  public static function create(array $values = []): CollectionInterface {
    // Delegate to the parent method. This is only overridden to provide the
    // correct return type.
    return parent::create($values);
  }

  /**
   * {@inheritdoc}
   */
  public function getSolutions(bool $onlyPublished = FALSE): array {
    $ids = $this->getSolutionIds($onlyPublished);
    if (empty($ids)) {
      return $ids;
    }
    return $this
      ->entityTypeManager()
      ->getStorage('node')
      ->loadMultiple($ids);
  }

  /**
   * {@inheritdoc}
   *
   * @todo Get rid of the $onlyPublished parameter as it is only used once.
   * @todo Probably get rid of the method as we will have a computed field.
   * @todo Make sure that access is enforced in the computed field.
   * @todo The ::getSolutions might end up as this method.
   */
  public function getSolutionIds(bool $onlyPublished = FALSE): array {
    // @todo Fix this after the computed field is created.
    // @todo The computed field should be in the solution module as should the
    //   subscriber that exports the data to the collection.
    $idsQuery = $this->entityTypeManager()->getStorage('node')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'solution')
      ->condition(OgGroupAudienceHelper::DEFAULT_FIELD, $this->id());

    if ($onlyPublished) {
      $idsQuery
        ->accessCheck()
        ->condition('status', 1);
    }

    return array_values($idsQuery->execute());
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
  public function getGlossarySettings(): array {
    $meta_entity = MetaEntity::loadOrCreate('collection_settings', $this);
    return [
      'link_only_first' => (bool) $meta_entity->get('glossary_link_only_first')->value,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function createMembership(?int $uid = NULL, ?string $role = 'member', ?string $state = NULL): OgMembershipInterface {
    // If the membership state is not defined, default to 'active' for open
    // collections, and 'pending' for closed collections.
    if (empty($state)) {
      $state = $this->isClosed() ? OgMembershipInterface::STATE_PENDING : OgMembershipInterface::STATE_ACTIVE;
    }

    return $this->createMembershipTrait($uid, $role, $state);
  }

  /**
   * {@inheritdoc}
   */
  public function isClosed(): bool {
    return (bool) $this->getMainPropertyValue('closed_group');
  }

  /**
   * {@inheritdoc}
   */
  public function getCodeOfConduct(): ?ContentEntityInterface {
    return $this->getSetting('code_of_conduct')->entity;
  }

  /**
   * {@inheritdoc}
   */
  public function hasCodeOfConductToAccept(): bool {
    // Open collections are not forcing users to accept their Code of Conduct.
    if (!$this->isClosed()) {
      return FALSE;
    }
    $codeOfConduct = $this->getCodeOfConduct();
    return $codeOfConduct &&
      (
        !$codeOfConduct instanceof EntityPublishedInterface ||
        $codeOfConduct->isPublished()
      );
  }

  /**
   * {@inheritdoc}
   */
  public function getNewMembershipSuccessMessage(OgMembershipInterface $membership): TranslatableMarkup {
    $parameters = [
      '%group' => $this->label(),
    ];
    return $membership->getState() === OgMembershipInterface::STATE_ACTIVE ?
      $this->t('You are now a member of %group.', $parameters) :
      $this->t('Your membership to the %group collection is under approval.', $parameters);
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
        return $this->t('You cannot join %group because your account has been blocked.', $parameters);

      case OgMembershipInterface::STATE_PENDING:
        return $this->t('You have already joined the %group collection but your membership still needs to be approved by a facilitator.', $parameters);

      default:
        return $this->t('You already are a member of %group.', $parameters);
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function doGetGroupContentIds(): array {
    $ids = ['node' => $this->getNodeGroupContent()];
    $solutions = $this->getSolutions();
    $ids = NestedArray::mergeDeep($ids, [
      'node' => [
        'solution' => array_keys($solutions),
      ],
    ]);
    foreach ($solutions as $solution) {
      $ids = NestedArray::mergeDeep($ids, $solution->getGroupContentIds());
    }
    return $ids;
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
    return 'website_url';
  }

  /**
   * {@inheritdoc}
   */
  public function getAbstractField(): string {
    return 'abstract';
  }

}
