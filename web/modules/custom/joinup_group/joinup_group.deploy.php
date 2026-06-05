<?php

/**
 * @file
 * Update hooks for the Joinup Group module.
 */

declare(strict_types=1);

use Drupal\joinup_group\Update\GroupMenuLinkMigration;

/**
 * Cleans up leftovers from previous tasks.
 */
function joinup_group_deploy_201400(): void {
  $helper = \Drupal::classResolver(GroupMenuLinkMigration::class);

  // In ISAICP-10031 the joinup_menu.documentation_file has been removed, but
  // removal was incomplete and some data is still available in the database.
  // @see https://citnet.tech.ec.europa.eu/CITnet/jira/browse/ISAICP-10031
  $helper->removeMenuLinkContentByRoute('joinup_menu.documentation_file');

  // Since ISAICP-7858 the navigation doesn't receive a new 'Members' link, and
  // existing links were disabled. The new modernized 'Members' link has been
  // moved in the group header. As this is a legacy feature, we remove the
  // unused links. We keep the links still in use.
  $helper->removeMenuLinkContentByRoute('entity.node.member_overview', FALSE);

  // Remove malformed (orphan) releases.
  $storage = \Drupal::entityTypeManager()->getStorage('node');
  $storage->delete($storage->loadMultiple([722176, 722441, 722464]));
}

/**
 * Convert custom page links from menu link content to group menu links.
 */
function joinup_group_deploy_201401(array &$sandbox): string {
  $helper = \Drupal::classResolver(GroupMenuLinkMigration::class);

  $helper->buildSandboxData($sandbox);

  $links = array_splice($sandbox['links'], 0, 25);
  $sandbox['progress'] += count($links);

  foreach ($links as $link) {
    $helper->processOneLink($link, $sandbox);
  }

  $sandbox['#finished'] = (int) empty($sandbox['links']);

  if ($sandbox['#finished'] === 1) {
    return "Finished converting $sandbox[total] custom page menu links. Took " . $helper->formatInterval($sandbox['start']);
  }

  return "Converted $sandbox[progress] out of $sandbox[total] custom page menu links ";
}

/**
 * Convert discussion links.
 */
function joinup_group_deploy_201402(array &$sandbox): string {
  return \Drupal::classResolver(GroupMenuLinkMigration::class)
    ->processGroupLinks($sandbox, 'discussion');
}

/**
 * Convert glossary links.
 */
function joinup_group_deploy_201403(array &$sandbox): string {
  return \Drupal::classResolver(GroupMenuLinkMigration::class)
    ->processGroupLinks($sandbox, 'glossary');
}

/**
 * Convert release history links.
 */
function joinup_group_deploy_201404(array &$sandbox): string {
  return \Drupal::classResolver(GroupMenuLinkMigration::class)
    ->processGroupLinks($sandbox, 'release');
}

/**
 * Convert website_link links.
 */
function joinup_group_deploy_201405(array &$sandbox): string {
  return \Drupal::classResolver(GroupMenuLinkMigration::class)
    ->processGroupLinks($sandbox, 'website_link');
}

/**
 * Convert overview links.
 */
function joinup_group_deploy_201406(array &$sandbox): string {
  return \Drupal::classResolver(GroupMenuLinkMigration::class)
    ->processGroupLinks($sandbox, 'overview');
}

/**
 * Convert EIF perspective links.
 */
function joinup_group_deploy_201407(array &$sandbox): string {
  return \Drupal::classResolver(GroupMenuLinkMigration::class)
    ->processGroupLinks($sandbox, 'eif_perspective');
}
