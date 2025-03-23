<?php

declare(strict_types=1);

namespace Drupal\joinup_group\Entity;

use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\joinup_core\Entity\OutdatedContentTrait;
use Drupal\meta_entity\Entity\MetaEntity;
use Drupal\og\Entity\OgRole;
use Drupal\og\MembershipManagerInterface;
use Drupal\og\OgGroupAudienceHelperInterface;
use Drupal\og\OgMembershipInterface;
use Drupal\og\OgRoleInterface;
use Drupal\user\Entity\User;
use Drupal\user\UserInterface;

/**
 * Reusable methods for group bundles.
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
      ->condition('entity_id', $this->id())
      ->condition('state', OgMembershipInterface::STATE_ACTIVE);
    $ids = $query->execute();

    if (empty($ids)) {
      return;
    }

    \Drupal::database()->delete('og_membership__roles')
      ->condition('entity_id', $ids, 'IN')
      ->condition('roles_target_id', $this->getFullMemberRoleId())
      ->execute();

    $this->invalidateMembershipsAndSelf($ids);
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
  public function getFullMemberRoleName(): string {
    return $this->getEntityTypeId() . '-' . $this->bundle() . '-full_member';

  }

  /**
   * {@inheritdoc}
   */
  protected function invalidateMembershipsAndSelf(array $membershipIds): void {
    $this->entityTypeManager()
      ->getStorage('og_membership')
      ->resetCache($membershipIds);

    $this->entityTypeManager()->getStorage('node')
      ->resetCache([$this->id()]);
  }

}
