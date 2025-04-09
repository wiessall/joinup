<?php

declare(strict_types=1);

namespace Drupal\code_of_conduct;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\collection\Entity\CollectionInterface;
use Drupal\joinup_group\Entity\GroupInterface;
use Drupal\og\OgMembershipInterface;

/**
 * Membership handler for Code of Conduct.
 */
final class CocMembershipHandler implements CocMembershipHandlerInterface {

  public function __construct(
    private readonly Connection $database,
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

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
    if (!$skipAccepted && $membershipsWithAcceptedCoc = $this->getFullMembershipsWithAcceptedCoc($group)) {
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
   * @param \Drupal\joinup_group\Entity\GroupInterface $group
   *   The group entity.
   *
   * @return array
   *   The membership IDs of full memberships with accepted CoC.
   */
  protected function getFullMembershipsWithAcceptedCoc(GroupInterface $group): array {
    assert($group instanceof CollectionInterface);
    $page = $group->getCodeOfConduct();
    if ($page === NULL) {
      return [];
    }

    $query = $this->entityTypeManager
      ->getStorage('og_membership')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('entity_type', $group->getEntityTypeId())
      ->condition('entity_id', $group->id())
      ->condition('state', [OgMembershipInterface::STATE_ACTIVE, OgMembershipInterface::STATE_PENDING], 'IN')
      ->condition('coc_document.target_id', $page->id())
      ->condition('coc_document.target_revision_id', $page->getRevisionId());

    return $query->execute();
  }

  /**
   * Revokes full memberships for a batch of membership IDs.
   *
   * @param \Drupal\joinup_group\Entity\GroupInterface $group
   *   The group entity.
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
