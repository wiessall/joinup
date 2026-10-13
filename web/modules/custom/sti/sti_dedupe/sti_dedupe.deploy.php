<?php

/**
 * @file
 * Deploy hooks for sti_dedupe.
 *
 * Run by drush deploy after config import, so they can rely on fields that
 * arrive through config/sync.
 */

declare(strict_types=1);

use Drupal\sti_dedupe\Exception\InvariantViolationException;

/**
 * Writes sameInnovationAs claims for every "same" cluster that lacks them.
 *
 * Restores the claims that an earlier revision of sti_dedupe_update_11002()
 * removed, from the decisions it recorded in their place, and completes
 * clusters linked before claims were written. Idempotent: clusters already
 * carrying every claim are left alone.
 */
function sti_dedupe_deploy_restore_same_as_claims(array &$sandbox): string {
  // Deploy hooks are procedural and offer no injection point.
  /** @var \Drupal\sti_dedupe\SameAsClaims $sameAs */
  $sameAs = \Drupal::service('sti_dedupe.same_as_claims');

  if (!isset($sandbox['clusters'])) {
    $sandbox['clusters'] = \Drupal::service('sti_dedupe.decisions')->sameClusters();
    $sandbox['total'] = count($sandbox['clusters']);
    $sandbox['added'] = 0;
    $sandbox['failed'] = [];
  }

  foreach (array_splice($sandbox['clusters'], 0, 20) as $cluster) {
    try {
      $sandbox['added'] += $sameAs->linkCluster($cluster);
      $sameAs->verify($cluster);
    }
    catch (\Throwable $e) {
      // verify() has already logged an invariant failure; log the rest here
      // and carry on, so one bad solution does not block every cluster.
      if (!$e instanceof InvariantViolationException) {
        \Drupal::logger('sti_dedupe')->error('Restoring sameInnovationAs claims for solutions @nids failed: @message', [
          '@nids' => implode(', ', $cluster),
          '@message' => $e->getMessage(),
        ]);
      }
      $sandbox['failed'][] = implode(',', $cluster);
    }
  }

  if ($sandbox['clusters']) {
    $sandbox['#finished'] = 1 - count($sandbox['clusters']) / max(1, $sandbox['total']);
    return '';
  }

  $sandbox['#finished'] = 1;
  if ($sandbox['failed']) {
    throw new \RuntimeException(sprintf('sameInnovationAs claims could not be restored for %d cluster(s): %s. See the sti_dedupe log; fix and run drush deploy:hook again.', count($sandbox['failed']), implode(' | ', $sandbox['failed'])));
  }
  return (string) t('Restored @added sameInnovationAs claim(s) across @total "same" cluster(s).', [
    '@added' => $sandbox['added'],
    '@total' => $sandbox['total'],
  ]);
}
