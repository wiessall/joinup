<?php

/**
 * @file
 * Deploy hooks for the Collection module.
 */

declare(strict_types=1);

/**
 * Fix stale pending draft revisions caused by child content deletion.
 */
function collection_deploy_201100(array &$sandbox): string {
  $storage = \Drupal::entityTypeManager()->getStorage('node');

  if (!isset($sandbox['nids'])) {
    $sandbox['nids'] = array_values($storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'collection')
      ->execute());
    $sandbox['total'] = count($sandbox['nids']);
    $sandbox['processed'] = 0;
  }

  $nid = array_shift($sandbox['nids']);
  $updatedRevisions = 0;

  /** @var \Drupal\node\NodeInterface $defaultRevision */
  $defaultRevision = $storage->load($nid);
  if ($defaultRevision) {
    $defaultChanged = $defaultRevision->getChangedTime();
    $defaultRevisionId = $defaultRevision->getRevisionId();

    // Check only revisions newer than the default (pending drafts) for a stale
    // changed timestamp.
    $revisionIds = $storage->getQuery()
      ->accessCheck(FALSE)
      ->allRevisions()
      ->condition('nid', $nid)
      ->condition('vid', $defaultRevisionId, '>')
      ->sort('vid', 'ASC')
      ->execute();

    foreach ($revisionIds as $revisionId => $unused) {
      /** @var \Drupal\node\NodeInterface $revision */
      $revision = $storage->loadRevision($revisionId);
      if (!$revision || $revision->getChangedTime() >= $defaultChanged) {
        continue;
      }

      $revision->skip_notification = TRUE;
      $revision->setNewRevision(FALSE);
      $revision->setChangedTime($defaultChanged + $updatedRevisions)->save();
      $updatedRevisions++;
    }
  }

  $sandbox['processed']++;
  $sandbox['#finished'] = $sandbox['total'] ? $sandbox['processed'] / $sandbox['total'] : 1;

  return "Processed collection {$nid} ({$sandbox['processed']} of {$sandbox['total']}): {$updatedRevisions} revision(s) updated.";
}
