<?php

declare(strict_types=1);

namespace Drupal\code_of_conduct;

use Drupal\collection\Entity\CollectionInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\custom_page\Entity\CustomPageInterface;
use Drupal\joinup_group\Entity\GroupInterface;
use Drupal\meta_entity\Entity\MetaEntityInterface;
use Drupal\og\OgMembershipInterface;

/**
 * Handler service for Code of Conduct.
 */
final class CodeOfConductHandler implements CodeOfConductHanlderInterface {

  use StringTranslationTrait;

  public function __construct(
    private readonly Connection $database,
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function onGroupUpdate(GroupInterface $group): void {
    // Calculate if we need to revoke or grant full memberships. When the
    // collection is updated and is changed from open to closed, AND there is a
    // Code of Conduct page, we need to revoke all full memberships. If the
    // collection is updated and is changed from closed to open, we need to
    // restore all full memberships. 2 special cases here: If the current saved
    // version is not a published one, we don't need to do anything. If the
    // collection is updated and the current version is published, we need to
    // compare with the last published version to decide if we need to revoke
    // or grant full memberships. If there is no previous published version, we
    // only act if the current version is a closed group.
    /** @var \Drupal\collection\Entity\CollectionInterface $group */
    if (!$group->isPublished() || !$group->hasPublishedCodeOfConduct()) {
      return;
    }
    $originalWasClosed = $this->wasLastPublishedRevisionClosed($group);
    if ($originalWasClosed === NULL && $group->isClosed() || ($originalWasClosed === FALSE && $group->isClosed())) {
      // Either there is no previous published version and the current version
      // is a closed group, or the previous published version was an open group
      // and the current version is a closed group.
      $this->revokeFullMemberships($group);
      \Drupal::messenger()->addWarning($this->t('All full memberships have been revoked. You need to accept the Code of Conduct to become a full member again.'));
    }
    elseif ($originalWasClosed === NULL && !$group->isClosed() || ($originalWasClosed && !$group->isClosed())) {
      // Either there is no previous published version and the current version
      // is an open group, or the previous published version was a closed group
      // and the current version is an open group.
      $this->restoreFullMemberships($group);
      \Drupal::messenger()->addWarning($this->t('All full memberships have been restored.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function onCodeOfConductUpdate(CustomPageInterface $customPage): void {
    if (!$customPage->isGroupCodeOfConductPage()) {
      return;
    }

    // The following cases have effects:
    // 1. The page was published and is becoming unpublished. Memberships are
    //    restored because CoC is no longer in effect.
    // 2. The page was unpublished and is becoming published. Memberships are
    //    revoked because CoC is now in effect.
    // 3. The page was updated from published to published. Memberships are
    //    revoked because they have to accept the new CoC.
    $original = $customPage->original;
    if (empty($original)) {
      return;
    }

    $group = $customPage->getGroup();
    if (($customPage->isPublished() && !$original->isPublished()) || ($customPage->isPublished() && $original->isPublished())) {
      $this->revokeFullMemberships($group);
    }
    elseif (!$customPage->isPublished() && $original->isPublished()) {
      $this->restoreFullMemberships($group);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function onGroupSettingsInsertOrUpdate(MetaEntityInterface $metaEntity, bool $insert = FALSE): void {
    if ($metaEntity->bundle() !== 'collection_settings') {
      return;
    }
    /** @var \Drupal\joinup_group\Entity\GroupInterface $group */
    $group = $metaEntity->getTargetEntity();

    // Open collections are not restricted by CoC.
    if (!$group->isClosed()) {
      return;
    }

    $codeOfConduct = $metaEntity->get('code_of_conduct');
    if ($insert && !$codeOfConduct->isEmpty()) {
      $this->revokeFullMemberships($group);
    }
    else {
      $original = $metaEntity?->original;
      if ($original && !$original->get('code_of_conduct')->equals($codeOfConduct)) {
        $codeOfConduct->isEmpty() ? $this->restoreFullMemberships($group) : $this->revokeFullMemberships($group);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function onGroupSettingsDelete(MetaEntityInterface $metaEntity): void {
    /** @var \Drupal\joinup_group\Entity\GroupInterface $group */
    $group = $metaEntity->getTargetEntity();
    if (empty($group)) {
      // This can happen when the deletion of the meta entity is the result of
      // the deletion of the parent entity.
      return;
    }
    $this->restoreFullMemberships($group);
  }

  /**
   * Checks if the last revision was closed.
   *
   * @param \Drupal\joinup_group\Entity\GroupInterface $group
   *   The group entity.
   *
   * @return bool|null
   *   TRUE if the last revision was closed, FALSE if it was not closed, or
   *   NULL if there is no previous published revision.
   */
  protected function wasLastPublishedRevisionClosed(GroupInterface $group): ?bool {
    $query = $this->database->select('node_field_revision', 'nr');
    $query->join('node_revision__closed_group', 'nrcg', 'nr.vid = nrcg.revision_id');
    $query->fields('nrcg', ['closed_group_value'])
      ->condition('nr.nid', $group->id())
      ->condition('nr.status', 1);
    if ($currentVid = $group->getRevisionId()) {
      $query->condition('nr.vid', $currentVid, '<');
    }
    $query->orderBy('nr.vid', 'DESC')
      ->range(0, 1);
    $result = $query->execute()->fetchField();
    return is_numeric($result) ? (bool) $result : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function revokeFullMemberships(GroupInterface $group, bool $skipAccepted = FALSE): void {
    $query = $this->entityTypeManager
      ->getStorage('og_membership')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('entity_type', $group->getEntityTypeId())
      ->condition('entity_id', $group->id())
      ->condition('state', [OgMembershipInterface::STATE_ACTIVE, OgMembershipInterface::STATE_PENDING], 'IN');
    if (!$skipAccepted && $membershipsWithAcceptedCoc = $this->getFullMembershipsWithAcceptedCoc()) {
      $query->condition('id', $membershipsWithAcceptedCoc, 'NOT IN');
    }

    $ids = $query->execute();
    if (empty($ids)) {
      return;
    }

    $idsToInvalidate = $ids;
    while (!empty($ids)) {
      $batch = array_splice($ids, 0, 500);
      $this->revokeBatchOfMemberships($group, $batch);
    }

    $this->invalidateMemberships($idsToInvalidate);
  }

  /**
   * Returns the membership IDs of full memberships with accepted CoC.
   *
   * @return array
   *   The membership IDs of full memberships with accepted CoC.
   */
  protected function getFullMembershipsWithAcceptedCoc(): array {
    assert($this instanceof CollectionInterface);
    $page = $this->getCodeOfConduct();
    if ($page === NULL) {
      return [];
    }

    $query = $this->entityTypeManager
      ->getStorage('og_membership')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('entity_type', $this->getEntityTypeId())
      ->condition('entity_id', $this->id())
      ->condition('state', [OgMembershipInterface::STATE_ACTIVE, OgMembershipInterface::STATE_PENDING], 'IN')
      ->condition('coc_document.target_id', $page->id())
      ->condition('coc_document.target_revision_id', $page->getRevisionId());

    return $query->execute();
  }

  /**
   * Revokes full memberships for a batch of membership IDs.
   *
   * @param int[] $ids
   *   The membership IDs to revoke.
   */
  protected function revokeBatchOfMemberships(GroupInterface $group, array $ids): void {
    $this->database
      ->delete('og_membership__roles')
      ->condition('entity_id', $ids, 'IN')
      ->condition('roles_target_id', $this->getFullMemberRoleId($group))
      ->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function restoreFullMemberships(GroupInterface $group): void {
    // Clean up any existing full memberships to avoid duplicates.
    $this->revokeFullMemberships($group, TRUE);
    $query = $this->database->select('og_membership', 'ogm');
    $query->leftJoin('og_membership__roles', 'ogmr', 'ogm.id = ogmr.entity_id');
    $query->fields('ogm');
    $query->fields('ogmr', ['delta', 'roles_target_id']);
    $query->condition('ogm.entity_type', $group->getEntityTypeId());
    $query->condition('ogm.entity_id', $group->id());
    $query->condition('state', [OgMembershipInterface::STATE_ACTIVE, OgMembershipInterface::STATE_PENDING], 'IN');

    $data = $query->execute()->fetchAll();
    if (empty($data)) {
      return;
    }

    $insertRows = [];
    foreach ($data as $row) {
      $nextDelta = $row->delta ? $row->delta + 1 : 0;
      if (isset($insertRows[$row->id])) {
        $insertRows[$row->id]['delta'] = max($nextDelta, $insertRows[$row->id]['delta']);
      }
      else {
        $insertRows[$row->id] = [
          'bundle' => 'default',
          'deleted' => 0,
          'entity_id' => $row->id,
          'revision_id' => $row->id,
          'langcode' => $row->language,
          'roles_target_id' => $this->getFullMemberRoleId($group),
          'delta' => $nextDelta,
        ];
      }
    }

    $query = $this->database->insert('og_membership__roles');
    $query->fields(['bundle', 'deleted', 'entity_id', 'revision_id', 'langcode', 'roles_target_id', 'delta']);
    foreach ($insertRows as $insertRow) {
      $query->values($insertRow);
    }
    $query->execute();
    $this->invalidateMemberships(array_keys($insertRows));
  }

  /**
   * {@inheritdoc}
   */
  public function getFullMemberRoleId(GroupInterface $group): string {
    return $group->getEntityTypeId() . '-' . $group->bundle() . '-full_member';
  }

  /**
   * Invalidates the memberships and the group entity.
   *
   * @param int[] $membershipIds
   *   The membership IDs to invalidate.
   */
  protected function invalidateMemberships(array $membershipIds): void {
    $ogMembershipStorage = $this->entityTypeManager->getStorage('og_membership');
    $ogMembershipStorage->resetCache($membershipIds);
  }

}
