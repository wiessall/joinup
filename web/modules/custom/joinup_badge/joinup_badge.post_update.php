<?php

/**
 * @file
 * Post-update functions for the Joinup Badge module.
 */

declare(strict_types=1);

use Drupal\joinup_badge\BadgeService;
use Drupal\og\OgMembershipInterface;

/**
 * Award the 'iec_member' badge to existing active members of the IEC.
 */
function joinup_badge_post_update_201300(array &$sandbox): string {
  // The UUID of the Interoperable Europe Community group node.
  // @see \Drupal\joinup_badge\Hook\BadgeHooks
  $iecUuid = 'ba527094-00bc-45de-98fb-c732c6c66fec';

  $entityTypeManager = \Drupal::entityTypeManager();
  $membershipStorage = $entityTypeManager->getStorage('og_membership');

  if (!isset($sandbox['ids'])) {
    $group = \Drupal::service('entity.repository')->loadEntityByUuid('node', $iecUuid);
    if (!$group) {
      $sandbox['#finished'] = 1;
      return 'Interoperable Europe Community group not found; nothing to do.';
    }

    $sandbox['ids'] = array_values($membershipStorage->getQuery()
      ->accessCheck(FALSE)
      ->condition('entity_type', $group->getEntityTypeId())
      ->condition('entity_id', $group->id())
      ->condition('state', OgMembershipInterface::STATE_ACTIVE)
      ->execute());
    $sandbox['total'] = count($sandbox['ids']);
    $sandbox['awarded'] = 0;

    if ($sandbox['total'] === 0) {
      $sandbox['#finished'] = 1;
      return 'No active members found; nothing to do.';
    }
  }

  /** @var \Drupal\joinup_badge\BadgeService $badgeService */
  $badgeService = \Drupal::service(BadgeService::class);
  $chunk = array_splice($sandbox['ids'], 0, 50);

  /** @var \Drupal\og\OgMembershipInterface[] $memberships */
  $memberships = $membershipStorage->loadMultiple($chunk);
  foreach ($memberships as $membership) {
    if ($user = $membership->getOwner()) {
      $badgeService->award('iec_member', $user);
      $sandbox['awarded']++;
    }
  }
  $membershipStorage->resetCache($chunk);

  $sandbox['#finished'] = empty($sandbox['ids']) ? 1 : ($sandbox['total'] - count($sandbox['ids'])) / $sandbox['total'];

  return sprintf('Processed %d of %d active IEC memberships; %d badges awarded so far.', $sandbox['total'] - count($sandbox['ids']), $sandbox['total'], $sandbox['awarded']);
}
