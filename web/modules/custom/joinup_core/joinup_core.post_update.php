<?php

/**
 * @file
 * Post update functions for Joinup.
 *
 * This should only contain update functions that rely on the Drupal API but
 * need to run _before_ the configuration is imported.
 *
 * For example this can be used to enable a new module that needs to have its
 * code available for the configuration to be successfully imported or updated.
 *
 * In most cases though update code should be placed in joinup_core.deploy.php.
 */

declare(strict_types = 1);

/**
 * Set the published_at to NULL where it currently is 2147483647.
 */
function joinup_core_post_update_110000(): void {
  $connection = \Drupal::database();
  $queries = [
    // Update nodes with multiple revisions that have at least one published
    // revision so the publication date is set to the timestamp of the first
    // published revision.
    [
      'query' => <<<SQL
UPDATE
  {node_field_revision} r,
  {node_revision} s,
  (
    SELECT
      nid,
      MIN(vid) as vid
    FROM {node_field_revision}
    WHERE published_at = 2147483647
    GROUP BY nid
    ORDER BY vid
  ) t
SET r.published_at = s.revision_timestamp
WHERE
  r.nid = t.nid
  AND s.nid = t.nid
  AND s.vid = t.vid
  AND r.vid >= t.vid;
SQL,
    ],

    // Update the revisions table so that nodes that have a single published
    // revision have the publication timestamp set to the creation timestamp.
    [
      'query' => <<<SQL
UPDATE {node_field_revision} r, (
  SELECT nid
  FROM {node_field_revision}
  GROUP BY nid
  HAVING COUNT(*) = 1
) s
SET r.published_at = r.created
WHERE
  r.nid = s.nid
  AND r.published_at = 2147483647
SQL,
    ],

    // Copy the publication date from the revisions table to the node table.
    [
      'query' => <<<SQL
UPDATE {node_field_data} d, {node_field_revision} r
SET d.published_at = r.published_at
WHERE d.vid = r.vid;
SQL,
    ],
  ];

  // Perform the operations in a single atomic transaction.
  $transaction = $connection->startTransaction();
  try {
    foreach ($queries as $query_data) {
      \Drupal::database()->query($query_data['query']);
    }
  }
  catch (Exception $e) {
    $transaction->rollBack();
    throw new Exception('Database error', 0, $e);
  }
}
