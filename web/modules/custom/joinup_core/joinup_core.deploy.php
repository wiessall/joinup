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

use Drupal\Core\Url;
use Drupal\joinup_oss_catalogue\OssCatalogueCollectionInterface;

/**
 * Update path alias for EU OSS catalogue.
 */
function joinup_core_deploy_111000(): void {
  /** @var \Drupal\collection\Entity\CollectionInterface $collection */
  $collection = \Drupal::entityTypeManager()->getStorage('rdf_entity')
    ->load(OssCatalogueCollectionInterface::COLLECTION_ENTITY_ID);

  $collection
    ?->set('field_ar_state', 'published')
      ->set('path', ['alias' => '/eu-oss-catalogue', 'pathauto' => 0])
      ->save();
}

/**
 * Update title of EU OSS catalogue collection.
 */
function joinup_core_deploy_111001(): void {
  /** @var \Drupal\collection\Entity\CollectionInterface $collection */
  $collection = \Drupal::entityTypeManager()->getStorage('rdf_entity')
    ->load(OssCatalogueCollectionInterface::COLLECTION_ENTITY_ID);

  $collection
    ?->set('label', 'EU Open Source Solutions Catalogue')
      ->save();
}

/**
 * Import content for EU OpenSource Software Solutions Catalogue.
 */
function joinup_core_deploy_111002(): void {
  /** @var \Drupal\Core\Extension\ModuleInstallerInterface $moduleInstaller */
  $moduleInstaller = \Drupal::service('module_installer');
  /** @var \Drupal\Core\Entity\EntityRepositoryInterface $entityRepository */
  $entityRepository = \Drupal::service('entity.repository');

  // Delete faulty meta entity.
  $entityRepository
    ->loadEntityByUuid('meta_entity', 'ad9be48e-7116-44b3-8df2-db8cb9343268')
    ?->delete();

  // Import content.
  $moduleInstaller->install(['default_content']);
  /** @var \Drupal\default_content\ImporterInterface $importer */
  \Drupal::service('default_content.importer')
    ->importContent('joinup_oss_catalogue', TRUE);
  $moduleInstaller->uninstall(['default_content']);

  // Disable auto-created left-side menu link.
  $landingPage = $entityRepository->loadEntityByUuid('node', '54acbd6f-47f0-4280-ad09-0a07c397318b');
  $linkPlugins = \Drupal::service('plugin.manager.menu.link')
    ->loadLinksByRoute('entity.node.canonical', ['node' => $landingPage->id()], 'ogmenu-3728');
  /** @var \Drupal\menu_link_content\Plugin\Menu\MenuLinkContent $linkPlugin */
  $linkPlugin = reset($linkPlugins);
  $entityRepository
    ->loadEntityByUuid('menu_link_content', $linkPlugin->getDerivativeId())
    ->setUnpublished()
    ->save();
}

/**
 * Rename menu items for EU OSS catalogue.
 */
function joinup_core_deploy_111003(): void {
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

/**
 * Make sure that oss_archived has a default value.
 */
function joinup_core_deploy_111004(array &$sandbox): string {
  $entityTypeManager = \Drupal::entityTypeManager();
  /** @var \Drupal\node\NodeStorage $nodeStorage */
  $nodeStorage = $entityTypeManager->getStorage('node');

  if (empty($sandbox['ids'])) {
    $results = $nodeStorage->getQuery()->accessCheck(FALSE)
      ->condition('type', 'oss_solution')
      ->notExists('oss_archived')
      ->execute();

    $sandbox['ids'] = array_values($results);
    $sandbox['progress'] = 0;
    $sandbox['count'] = count($sandbox['ids']);
  }

  $ids = array_splice($sandbox['ids'], 0, 50);
  foreach ($nodeStorage->loadMultiple($ids) as $solution) {
    $solution->set('oss_archived', 0);
    $solution->save();
  }

  $sandbox['progress'] += count($ids);
  $sandbox['#finished'] = (int) empty($sandbox['ids']);

  return "Processed {$sandbox['progress']} out of {$sandbox['count']} solutions.";
}

/**
 * Delete stale page.
 */
function joinup_core_deploy_111005(): void {
  \Drupal::entityTypeManager()->getStorage('node')->load(703013)?->delete();
}
