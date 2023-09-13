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

use Drupal\Core\Url;
use Drupal\joinup_oss_catalogue\OssCatalogueCollectionInterface;

/**
 * Update path alias for EU OSS catalogue.
 */
function joinup_core_deploy_109700(): void {
  /** @var \Drupal\collection\Entity\CollectionInterface $collection */
  $collection = \Drupal::entityTypeManager()->getStorage('rdf_entity')
    ->load(OssCatalogueCollectionInterface::COLLECTION_ENTITY_ID);

  $collection
    ?->set('field_ar_state', 'validated')
      ->set('path', ['alias' => '/eu-oss-catalogue', 'pathauto' => 0])
      ->save();
}

/**
 * Rename menu items for EU OSS catalogue.
 */
function joinup_core_deploy_109704(): void {
  $entityTypeManager = \Drupal::entityTypeManager();
  /** @var  \Drupal\menu_link_content\MenuLinkContentStorageInterface $menuLinkContentStorage */
  $menuLinkContentStorage = $entityTypeManager->getStorage('menu_link_content');

  $menuName = 'ogmenu-3728';
  // Collect the IDs of links to the custom page.
  $ids = $menuLinkContentStorage->getQuery()->accessCheck(FALSE)
    ->condition('bundle', 'menu_link_content')
    ->condition('menu_name', $menuName)
    ->execute();

  $links = $menuLinkContentStorage->loadMultiple($ids);
  foreach ($links as $link) {
    $title = $link->getTitle();
    if ($title === 'About' || $title === 'Members') {
      $link->setUnpublished()->save();
    }
    elseif ($title === 'Overview') {
      $link->set('title', 'Welcome')->save();
    }
  }
  $link = [
    'uri' => Url::fromRoute('view.search_oss_catalogue.search')->toUriString(),
  ];
  $menuLinkContentStorage->create([
    'title' => t('Search'),
    'menu_name' => $menuName,
    'link' => $link,
    'weight' => -6,
  ])->save();
}
