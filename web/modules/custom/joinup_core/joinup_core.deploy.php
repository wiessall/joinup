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

declare(strict_types=1);

use Drupal\menu_link_content\MenuLinkContentInterface;

/**
 * Add navbar link to sitewide discussions page.
 */
function joinup_core_deploy_111000(): void {
  $menuLinkContentStorage = \Drupal::entityTypeManager()->getStorage('menu_link_content');
  $parentId = key(
    $menuLinkContentStorage->getQuery()
      ->accessCheck(FALSE)
      ->condition('title', 'Support Centre')
      ->condition('uuid', '384488ef-3150-421c-8234-c94aea24323a')
      ->execute()
  );
  $parent = $menuLinkContentStorage->load($parentId);
  assert($parent instanceof MenuLinkContentInterface);
  $parent->set('expanded', TRUE)->save();
  $forumLink = $menuLinkContentStorage->create([
    'title' => 'Discussions',
    'menu_name' => 'navbar',
    'link' => 'internal:/discussions',
    'parent' => "menu_link_content:{$parent->uuid()}",
  ]);
  assert($forumLink instanceof MenuLinkContentInterface);
  $forumLink->save();
}
