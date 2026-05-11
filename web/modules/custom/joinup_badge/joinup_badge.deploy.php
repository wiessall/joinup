<?php

/**
 * @file
 * Deployment hooks for the Joinup Badge module.
 */

declare(strict_types=1);

use Drupal\joinup_badge\PortalContributorBadgeEvaluator;

/**
 * Award the 'portal_contributor' badge to currently eligible users.
 */
function joinup_badge_deploy_201301(array &$sandbox): string {
  $bundles = [
    'solution',
    'collection',
    'event',
    'news',
    'discussion',
    'document',
    'assessment_report',
  ];
  $cutoff = (new \DateTimeImmutable())->modify('-2 months')->getTimestamp();

  if (!isset($sandbox['uids'])) {
    $database = \Drupal::database();

    $node_uids = $database->select('node_field_data', 'n')
      ->fields('n', ['uid'])
      ->condition('n.status', 1)
      ->condition('n.type', $bundles, 'IN')
      ->condition('n.created', $cutoff, '>=')
      ->distinct()
      ->execute()
      ->fetchCol();

    $comment_uids = $database->select('comment_field_data', 'c')
      ->fields('c', ['uid'])
      ->condition('c.status', 1)
      ->condition('c.created', $cutoff, '>=')
      ->distinct()
      ->execute()
      ->fetchCol();

    $uids = array_unique(array_map('intval', array_merge($node_uids, $comment_uids)));
    // Anonymous publications cannot earn a badge.
    $sandbox['uids'] = array_values(array_filter($uids, fn(int $uid): bool => $uid > 0));
    $sandbox['total'] = count($sandbox['uids']);
    $sandbox['processed'] = 0;
  }

  $batch = array_splice($sandbox['uids'], 0, 50);
  if ($batch) {
    $evaluator = \Drupal::service(PortalContributorBadgeEvaluator::class);
    $user_storage = \Drupal::entityTypeManager()->getStorage('user');
    foreach ($user_storage->loadMultiple($batch) as $user) {
      $evaluator->evaluate($user);
    }
    $sandbox['processed'] += count($batch);
  }

  $sandbox['#finished'] = $sandbox['total'] === 0 ? 1 : ($sandbox['processed'] / $sandbox['total']);

  return sprintf('Evaluated %d/%d users for the portal_contributor badge.', $sandbox['processed'], $sandbox['total']);
}
