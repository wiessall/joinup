<?php

/**
 * @file
 * Deploy functions for Joinup.
 *
 * This should only contain update functions that rely on the Drupal API and
 * need to run _after_ the configuration is imported.
 *
 * This is applicable in most cases. However, in case the update code enables
 * some functionality that is required for configuration to be successfully
 * imported, it should instead be placed in joinup_core.post_update.php.
 */

declare(strict_types = 1);

use Drupal\pathauto\PathautoState;

/**
 * Fix Interoperable Europe news creation link.
 */
function joinup_core_deploy_108900(): void {
  $entityTypeManager = \Drupal::entityTypeManager();
  $entityTypeManager->getStorage('node')->load(704825)->set('path', [
    'pathauto' => PathautoState::SKIP,
    'alias' => '/interoperable-europe/latest-news',
  ])->save();
  $entityTypeManager->getStorage('path_alias')->load(84170)->delete();
}

/**
 * Cleanup the spam files from the last 7 days.
 */
function joinup_core_deploy_108901(array &$sandbox = []): string {
  if (empty($sandbox['file_ids'])) {
    // Get files of blocked users from past week.
    $query = \Drupal::database()->select('file_managed', 'f');
    $query->join('users_field_data', 'u', 'f.uid = u.uid');
    $query->fields('f', ['fid']);
    $query->condition('u.status', 0);
    $query->condition('u.created', strtotime('-7 days'), '>=');
    $sandbox['file_ids'] = $query->execute()->fetchCol();

    $sandbox['max'] = count($sandbox['file_ids']);
    $sandbox['current'] = 0;
  }

  $batch_size = 50;
  $file_ids = array_splice($sandbox['file_ids'], 0, $batch_size);
  $file_storage = \Drupal::entityTypeManager()->getStorage('file');
  $file_url_generator = \Drupal::getContainer()->get('file_url_generator');
  foreach ($file_storage->loadMultiple($file_ids) as $file) {
    $file->delete();

    // Ensure that the actual file does not exist.
    $file_uri = $file->getFileUri();
    $file_url_generator->generateAbsoluteString($file_uri);
    if (file_exists($file_uri)) {
      unlink($file_uri);
    }
  }

  $sandbox['current'] += count($file_ids);
  $sandbox['#finished'] = $sandbox['current'] / $sandbox['max'];

  return "Removed {$sandbox['current']} of {$sandbox['max']} files.";
}

/**
 * Remove files without database representation.
 */
function joinup_core_deploy_108902(): string {
  /** @var \Drupal\Core\File\FileSystemInterface $file_system */
  $file_system = \Drupal::service('file_system');
  $count = 0;
  // These files entries in {file_managed} table were already deleted.
  for ($i = 1; $i <= 452; $i++) {
    $file = "public://document/2022-12/free-doawnlaod-$i.pdf";
    if (file_exists($file)) {
      $file_system->delete($file);
      $count++;
    }
  }
  return "$count unmanaged files deleted";
}

/**
 * Delete content created by spammers.
 */
function joinup_core_deploy_108903(array &$sandbox = []): string {
  if (!isset($sandbox['items'])) {
    $sandbox['items'] = [];
    $db = \Drupal::database();

    // Blocked users whose accounts are created after 2nd of December 2022.
    $uids = $db->query("SELECT u.uid FROM {users_field_data} u WHERE u.status = 0 AND u.uid > 1 AND u.created > 1669327200")
      ->fetchCol();

    $sql_args = [':uids[]' => $uids];
    $sparql_args = implode(' ', array_map(function ($uid): string {
      return '"' . $uid . '"^^xsd:integer';
    }, $uids));

    // Add all nodes owned by spam users to sandbox.
    $nids = $db->query("SELECT nid FROM {node_field_data} WHERE uid IN(:uids[])", $sql_args)->fetchCol();
    foreach ($nids as $nid) {
      $sandbox['items'][] = ['node', $nid];
    }

    // Add all RDF entities owned by spam users to sandbox.
    $rows = \Drupal::service('sparql.endpoint')->query("SELECT DISTINCT(?id)
      FROM <http://joinup.eu/collection/published>
      FROM <http://joinup.eu/collection/draft>
      FROM <http://joinup.eu/solution/published>
      FROM <http://joinup.eu/solution/draft>
      FROM <http://joinup.eu/asset_release/published>
      FROM <http://joinup.eu/asset_release/draft>
      WHERE {
        GRAPH ?g {
          ?id ?p ?o .
          ?id <http://joinup.eu/owner/uid> ?o .
          VALUES ?id {{$sparql_args}} .
        }
      }");
    foreach ($rows as $row) {
      $sandbox['items'][] = ['rdf_entity', (string) $row->id];
    }

    // Add all files owned by spam users to sandbox.
    $fids = $db->query("SELECT fid FROM {file_managed} WHERE uid IN(:uids[])", $sql_args)->fetchCol();
    foreach ($fids as $fid) {
      $sandbox['items'][] = ['file', $fid];
    }
    $sandbox['processed'] = 0;
    $sandbox['total'] = count($sandbox['items']);
  }

  $items = array_splice($sandbox['items'], 0, 50);
  foreach ($items as $item) {
    [$entity_type_id, $id] = $item;
    $storage = \Drupal::entityTypeManager()->getStorage($entity_type_id);
    $entity = $storage->load($id);
    $entity->skip_notification = TRUE;
    $entity->delete();
  }
  $sandbox['processed'] += count($items);

  $sandbox['#finished'] = (int) empty($sandbox['items']);
  return "Deleted {$sandbox['processed']} content entries out of {$sandbox['total']}";
}
