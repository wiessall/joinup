<?php

/**
 * @file
 * Update hooks for the Joinup Group module.
 */

declare(strict_types=1);

use Drupal\joinup_discussion\Hook\JoinupDiscussionGroupMenuLinksHooks;
use Drupal\joinup_group\Update\GroupMenuLinksMigration;

/**
 * Cleans up leftovers from previous tasks.
 */
function joinup_group_deploy_201400(): void {
  // In ISAICP-10031 the joinup_menu.documentation_file has been removed, but
  // removal was incomplete and some data is still available in the database.
  // @see https://citnet.tech.ec.europa.eu/CITnet/jira/browse/ISAICP-10031
  \Drupal::classResolver()
    ->getInstanceFromDefinition(GroupMenuLinkMigration::class)
    ->removeMenuLinkContentByRoute('joinup_menu.documentation_file');

  // Remove malformed (orphan) releases.
  $storage = \Drupal::entityTypeManager()->getStorage('node');
  $storage->delete($storage->loadMultiple([722176, 722441, 722464]));
}

/**
 * Convert custom page links from menu link content to group menu links.
 */
function joinup_group_deploy_201401(array &$sandbox): string {
  $helper = \Drupal::classResolver()->getInstanceFromDefinition(GroupMenuLinkMigration::class);

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
    return "Finished converting $sandbox[total] custom page menu links. Took {$diff->format('%im %ss')}";
  }

  return "Converted $sandbox[progress] out of $sandbox[total] custom page menu links ";
}

/**
 * Convert discussion links.
 */
function joinup_group_deploy_201402(array &$sandbox): string {
  $storage = \Drupal::entityTypeManager()->getStorage('node');
  $hookService = \Drupal::service(JoinupDiscussionGroupMenuLinksHooks::class);

  if (!isset($sandbox['discussions'])) {
    $db = \Drupal::database();
/**
 * Convert glossary links.
 */
function joinup_group_deploy_201403(array &$sandbox): string {
  return \Drupal::classResolver()
    ->getInstanceFromDefinition(GroupMenuLinkMigration::class)
    ->processGroupContentLinks(
      $sandbox,
      'glossary',
      'glossary',
      'navigation',
      'entity.node.glossary',
      'Glossary',
      -7,
    );
}

/**
 * Add glossary links to solutions.
 */
function joinup_group_deploy_201404(array &$sandbox): string {
  $storage = \Drupal::entityTypeManager()->getStorage('node');

  if (!isset($sandbox['ids'])) {
    $sql = <<<SQL
      SELECT DISTINCT(og_audience_target_id)
      FROM {node__og_audience}
      WHERE bundle = 'glossary'
      SQL;
    $sandbox['ids'] = \Drupal::database()->query($sql)->fetchCol();
    $sandbox['total'] = count($sandbox['ids']);
    $sandbox['progress'] = 0;
  }

  $id = array_shift($sandbox['ids']);
  $sandbox['progress']++;
  $solutions = $storage->loadByProperties(['og_audience' => $id, 'type' => 'solution']);
  foreach ($solutions as $solution) {
    assert($solution instanceof SolutionInterface);
    \Drupal::service(GroupMenuLinkHooks::class)
      ->addGroupLink('glossary', $solution, 'navigation', 'entity.node.glossary', 'Glossary', -7, [
        'route_parameters' => ['node' => $id],
        'enabled' => FALSE,
      ]);
  }
  $sandbox['#finished'] = (int) empty($sandbox['ids']);

  return "Converted $sandbox[progress] out of $sandbox[total] solution glossary links";
}

/**
 * Convert release history links.
 */
function joinup_group_deploy_201405(array &$sandbox): string {
  return \Drupal::classResolver()
    ->getInstanceFromDefinition(GroupMenuLinkMigration::class)
    ->processGroupContentLinks(
      $sandbox,
      'release',
      'release',
      'quick_links',
      'joinup_release.solution_release.overview',
      'Release history',
    );
}

/**
 * Convert website_link links.
 */
function joinup_group_deploy_201406(array &$sandbox): string {
  return \Drupal::classResolver()
    ->getInstanceFromDefinition(GroupMenuLinkMigration::class)
    ->processGroupLinks(
      $sandbox,
      'website_link',
      'quick_links',
      'joinup_menu.website_link',
      'Website link',
    );
}

/**
 * Convert overview links.
 */
function joinup_group_deploy_201407(array &$sandbox): string {
  return \Drupal::classResolver()
    ->getInstanceFromDefinition(GroupMenuLinkMigration::class)
    ->processGroupLinks(
      $sandbox,
      'overview',
      'navigation',
      'entity.node.canonical',
      static fn(GroupInterface $group) => $group->getSetting('overview_label')->value ?? 'Overview',
    );
}

/**
 * Remove unused legacy members group links.
 */
function joinup_group_deploy_201408(): void {
  // Since ISAICP-7858 the navigation doesn't receive a new 'Members' link, and
  // existing links were disabled. The new modernized 'Members' link has been
  // moved in the group header. As this is a legacy feature, we remove the
  // unused links. We keep the 2 links still in use.
  $helper = \Drupal::classResolver() ->getInstanceFromDefinition(GroupMenuLinkMigration::class);
  $sql = <<<SQL
    SELECT mt.mlid, m.id
    FROM {menu_tree} mt
    INNER JOIN {menu_link_content} m ON SUBSTRING(mt.id, 19) = m.uuid
    WHERE mt.menu_name LIKE 'ogmenu-%'
    AND mt.route_name = 'entity.node.member_overview'
    AND mt.enabled = '0'
    SQL;
  $ids = \Drupal::database()->query($sql)->fetchAllKeyed();
  $helper->removeMenuLinkContent(array_values($ids), array_keys($ids));
}
