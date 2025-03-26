<?php

declare(strict_types=1);

namespace Drupal\joinup_group\Entity;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\Sql\SqlEntityStorageInterface;
use Drupal\og\OgMembershipInterface;

/**
 * Reusable methods for group bundles related to memberships and CoC.
 */
trait CodeOfConductTrait {

  /**
   * {@inheritdoc}
   */
  public function revokeFullMemberships(): void {
    $ogMembershipStorage = $this->getOgMembershipStorage();
    $query = $ogMembershipStorage
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('entity_type', $this->getEntityTypeId())
      ->condition('entity_id', $this->id());
    $ids = $query->execute();
    if (empty($ids)) {
      return;
    }

    $idsToInvalidate = $ids;
    while (!empty($ids)) {
      $batch = array_splice($ids, 0, 500);
      $this->revokeBatchOfMemberships($batch);
    }

    $this->invalidateMembershipsAndSelf($idsToInvalidate);
  }

  /**
   * Revokes full memberships for a batch of membership IDs.
   *
   * @param int[] $ids
   *   The membership IDs to revoke.
   */
  protected function revokeBatchOfMemberships(array $ids): void {
    \Drupal::database()->delete('og_membership__roles')
      ->condition('entity_id', $ids, 'IN')
      ->condition('roles_target_id', $this->getFullMemberRoleId())
      ->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function restoreFullMemberships(): void {
    // Clean up any existing full memberships to avoid duplicates.
    $this->revokeFullMemberships();
    $database = \Drupal::database();
    $query = $database->select('og_membership', 'ogm');
    $query->leftJoin('og_membership__roles', 'ogmr', 'ogm.id = ogmr.entity_id');
    $query->fields('ogm');
    $query->fields('ogmr', ['delta', 'roles_target_id']);
    $query->condition('ogm.entity_type', $this->getEntityTypeId());
    $query->condition('ogm.entity_id', $this->id());
    $query->condition('ogm.state', OgMembershipInterface::STATE_ACTIVE);

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
          'roles_target_id' => $this->getFullMemberRoleId(),
          'delta' => $nextDelta,
        ];
      }
    }

    $query = $database->insert('og_membership__roles');
    $query->fields(['bundle', 'deleted', 'entity_id', 'revision_id', 'langcode', 'roles_target_id', 'delta']);
    foreach ($insertRows as $insertRow) {
      $query->values($insertRow);
    }
    $query->execute();
    $this->invalidateMembershipsAndSelf(array_keys($insertRows));
  }

  /**
   * {@inheritdoc}
   */
  public function getFullMemberRoleId(): string {
    return $this->getEntityTypeId() . '-' . $this->bundle() . '-full_member';

  }

  /**
   * Invalidates the memberships and the group entity.
   *
   * @param int[] $membershipIds
   *   The membership IDs to invalidate.
   */
  protected function invalidateMembershipsAndSelf(array $membershipIds): void {
    $this->entityTypeManager()
      ->getStorage('og_membership')
      ->resetCache($membershipIds);

    $this->entityTypeManager()->getStorage('node')
      ->resetCache([$this->id()]);
  }

  /**
   * Returns the storage handler for the 'og_membership' entity type.
   *
   * @return \Drupal\Core\Entity\EntityStorageInterface
   *   The storage handler.
   */
  protected function getOgMembershipStorage(): EntityStorageInterface {
    $storage = $this->entityTypeManager()->getStorage('og_membership');
    // The Interoperable Europe Portal entity types are only supporting SQL
    // storage. This check is heads-up in the very unlikely case when the entity
    // type is switched to a non-SQL storage. Making sure we only run on SQL
    // storage allows us to use direct SQL queries to improve the performance of
    // this task that might be very intensive with entity API.
    \assert($storage instanceof SqlEntityStorageInterface);
    return $storage;
  }

}
