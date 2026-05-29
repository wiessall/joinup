<?php

/**
 * @file
 * Update hooks for the Joinup Group module.
 */

declare(strict_types=1);

use Drupal\joinup_discussion\Hook\JoinupDiscussionGroupMenuLinksHooks;
use Drupal\joinup_group\Update\GroupMenuLinksMigration;

/**
 * Convert custom page links from menu link content to group menu links.
 */
function joinup_group_deploy_201400(array &$sandbox): string {
  $helper = \Drupal::classResolver()->getInstanceFromDefinition(GroupMenuLinksMigration::class);

  $helper->buildSandboxData($sandbox);

  $links = array_splice($sandbox['links'], 0, 25);
  $sandbox['progress'] += count($links);

  foreach ($links as $link) {
    $helper->processOneLink($link, $sandbox);
  }

  $sandbox['#finished'] = (int) empty($sandbox['links']);

  if ($sandbox['#finished'] === 1) {
    // @todo REMOVE BABEL ENTRIES?
    $stop = new \DateTimeImmutable();
    $diff = $stop->diff($sandbox['start']);
    return "Finished converting $sandbox[total] menu links. Took {$diff->format('%im %ss')}";
  }

  return "Converted $sandbox[progress] out of $sandbox[total] menu links ";
}

/**
 * Convert discussion links.
 */
function joinup_group_deploy_201401(array &$sandbox): string {
  $storage = \Drupal::entityTypeManager()->getStorage('node');
  $hookService = \Drupal::service(JoinupDiscussionGroupMenuLinksHooks::class);

  if (!isset($sandbox['discussions'])) {
    $db = \Drupal::database();

    // Get the mapping menu_tree.mlid and menu_link_content.id.
    $sql = <<<SQL
        SELECT mt.mlid, m.id
        FROM {menu_tree} mt
        INNER JOIN {menu_link_content} m ON SUBSTRING(mt.id, 19) = m.uuid
        WHERE mt.menu_name LIKE 'ogmenu-%'
        AND mt.route_name = 'view.discussions.overview'
        SQL;
    $ids = $db->query($sql)->fetchAllKeyed();

    // Clean up menu_link_content tables.
    foreach (['', '_data', '_field_revision', '_revision'] as $table) {
      $db->delete("menu_link_content$table")
        ->condition('id', array_values($ids), 'IN')
        ->execute();
    }
    // Clean up the menu tree table.
    $db->delete('menu_tree')
      ->condition('mlid', array_keys($ids), 'IN')
      ->execute();

    // Pick up the first discussion ID from each group.
    $sql = <<<SQL
      SELECT MIN(entity_id)
      FROM {node__og_audience}
      WHERE bundle = 'discussion'
      GROUP BY og_audience_target_id
      SQL;
    $sandbox['discussions'] = $db->query($sql)->fetchCol();
    $sandbox['total'] = count($sandbox['discussions']);
    $sandbox['progress'] = 0;
  }

  $nids = array_splice($sandbox['discussions'], 0, 10);
  $sandbox['progress'] += count($nids);
  foreach ($storage->loadMultiple($nids) as $discussion) {
    $hookService->addGroupDiscussionLink($discussion);
  }

  $sandbox['#finished'] = (int) empty($sandbox['discussions']);

  return "Created $sandbox[progress] out of $sandbox[total] discussion links";
}
