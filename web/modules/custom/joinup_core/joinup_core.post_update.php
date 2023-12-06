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
 * Remove 'About' from menu.
 */
function joinup_core_post_update_110000(array &$sandbox): string {
  $entityTypeManager = \Drupal::entityTypeManager();
  /** @var  \Drupal\menu_link_content\MenuLinkContentStorageInterface $menuLinkContentStorage */
  $menuLinkContentStorage = $entityTypeManager->getStorage('menu_link_content');

  if (!isset($sandbox['data'])) {
    $query = $menuLinkContentStorage->getQuery()->accessCheck(FALSE);

    $sandbox['data'] = $query
      ->condition('link__uri', 'route:entity.rdf_entity.about_page%', 'LIKE')
      ->execute();

    $sandbox['progress'] = 0;
    $sandbox['count'] = count($sandbox['data']);
  }

  $spliced = array_splice($sandbox['data'], 0, 300);

  /** @var \Drupal\menu_link_content\MenuLinkContentInterface[] $links */
  $links = $menuLinkContentStorage->loadMultiple($spliced);
  foreach ($links as $link) {
    $link->delete();

    $sandbox['progress']++;
  }

  $sandbox['#finished'] = (int) empty($sandbox['data']);
  return "Processed {$sandbox['progress']} out of {$sandbox['count']}.";
}

/**
 * Disable 'Members' and 'EIF Perspective' from menu.
 */
function joinup_core_post_update_110001(array &$sandbox): string {
  $entityTypeManager = \Drupal::entityTypeManager();
  /** @var  \Drupal\menu_link_content\MenuLinkContentStorageInterface $menuLinkContentStorage */
  $menuLinkContentStorage = $entityTypeManager->getStorage('menu_link_content');

  if (!isset($sandbox['data'])) {
    $query = $menuLinkContentStorage->getQuery()->accessCheck(FALSE);
    $or = $query->orConditionGroup()
      ->condition('link__uri', 'route:entity.rdf_entity.member_overview%', 'LIKE')
      ->condition('link__uri', 'route:entity.rdf_entity.eif_perspective%', 'LIKE');

    $sandbox['data'] = $query
      ->condition($or)
      ->condition('enabled', 1)
      ->condition('menu_name', 'ogmenu-%', 'LIKE')
      ->execute();

    $sandbox['progress'] = 0;
    $sandbox['count'] = count($sandbox['data']);
  }

  $spliced = array_splice($sandbox['data'], 0, 300);

  /** @var \Drupal\menu_link_content\MenuLinkContentInterface[] $links */
  $links = $menuLinkContentStorage->loadMultiple($spliced);
  foreach ($links as $link) {
    $link->set('enabled', 0);
    $link->save();

    $sandbox['progress']++;
  }

  $sandbox['#finished'] = (int) empty($sandbox['data']);
  return "Processed {$sandbox['progress']} out of {$sandbox['count']}.";
}
