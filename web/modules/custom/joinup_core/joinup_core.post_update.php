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

use Drupal\sparql_entity_storage\UriEncoder;

/**
 * Remove 'About' from menu.
 */
function joinup_core_post_update_110100(array &$sandbox): string {
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
function joinup_core_post_update_110101(array &$sandbox): string {
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

/**
 * Redirect removed about pages to canonical page.
 */
function joinup_core_post_update_110102(array &$sandbox): string {
  /** @var \Drupal\sparql_entity_storage\SparqlEntityStorageInterface $storage */
  $rdfStorage = \Drupal::entityTypeManager()->getStorage('rdf_entity');
  /** @var \Drupal\Core\Entity\EntityStorageInterface $redirectStorage */
  $redirectStorage = \Drupal::entityTypeManager()->getStorage('redirect');
  /** @var \Drupal\path_alias\AliasManagerInterface $aliasManager */
  $aliasManager = \Drupal::service('path_alias.manager');

  if (!isset($sandbox['data'])) {
    $sandbox['data'] = $rdfStorage->getQuery()->accessCheck(FALSE)
      ->condition('rid', ['collection', 'solution'], 'IN')
      ->execute();

    $sandbox['progress'] = 0;
    $sandbox['count'] = count($sandbox['data']);
  }

  $spliced = array_splice($sandbox['data'], 0, 300);

  /** @var \Drupal\rdf_entity\RdfInterface[] $groups */
  $groups = $rdfStorage->loadMultiple($spliced);
  foreach ($groups as $group) {
    $path = '/rdf_entity/' . UriEncoder::encodeUrl($group->id());
    $alias = $aliasManager->getAliasByPath($path);
    if (!$alias) {
      continue;
    }

    try {
      $redirectStorage->create([
        'redirect_source' => ltrim($alias . '/about', '/'),
        'redirect_redirect' => 'internal:' . $path,
        'status_code' => '301',
        'language' => 'en',
      ])->save();
    }
    catch (\Exception $e) {
      // Simple skip as some redirects already exists.
    }

    $sandbox['progress']++;
  }

  $sandbox['#finished'] = (int) empty($sandbox['data']);
  return "Processed {$sandbox['progress']} out of {$sandbox['count']}.";
}
