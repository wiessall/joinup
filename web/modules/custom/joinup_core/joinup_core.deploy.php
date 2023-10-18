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
 * Change the state name of Releases from 'validated' to 'published'.
 */
function joinup_core_deploy_109800(): string {
  $database = \Drupal::getContainer()->get('sparql.endpoint');
  $workflow_urls = [
    'http://joinup.eu/isr/workflow_status' => [
      'http://joinup.eu/asset_release/published',
      'http://joinup.eu/asset_release/draft',
    ],
  ];

  foreach ($workflow_urls as $predicate => $graphs) {
    foreach ($graphs as $graph) {
      $update_query = <<<SPARQL
WITH <{$graph}>
DELETE {
  ?s <{$predicate}> "validated"^^<http://www.w3.org/2001/XMLSchema#string> .
}
INSERT {
  ?s <{$predicate}> "published"^^<http://www.w3.org/2001/XMLSchema#string> .
}
WHERE {
  ?s <{$predicate}> "validated"^^<http://www.w3.org/2001/XMLSchema#string> .
}
SPARQL;
      $database->query($update_query);
    }
  }

  return 'Changed the state name of all RDF entities from "validated" to "published".';
}

/**
 * Fix the contact information integer values (UID).
 */
function joinup_core_deploy_109801(): string {
  $database = \Drupal::getContainer()->get('sparql.endpoint');
  $update_query = <<<SPARQL
PREFIX xsd: <http://www.w3.org/2001/XMLSchema#>
WITH <http://joinup.eu/contact-information/published>
DELETE { ?s <http://joinup.eu/owner/uid> ?o }
INSERT { ?s <http://joinup.eu/owner/uid> ?int }
WHERE {
  ?s <http://joinup.eu/owner/uid> ?o .
  FILTER ( datatype(?o) = <integer> ) .
  BIND (xsd:integer(?o) as ?int) .
}
SPARQL;

  $database->query($update_query);

  return 'Changed the UID of all RDF entities from string to integer.';
}

/**
 * Remove the non-paired users.
 */
function joinup_core_deploy_109802(): string {
  $database = \Drupal::database();
  $query = $database->select('users', 'u')
    ->fields('u', ['uid'])
    ->condition('u.uid', 1, '>');
  $query->leftJoin('authmap', 'a', 'u.uid = a.uid');
  $query->condition('a.uid', NULL, 'IS NULL');

  $usersIds = $query->execute()->fetchCol();
  $toRemove = count($usersIds);

  $queue = \Drupal::queue('joinup_removed_users');
  $queue->createQueue();

  while (!empty($usersIds)) {
    $ids = array_splice($usersIds, 0, 10);
    $queue->createItem($ids);
  }

  return 'Add ' . $toRemove . ' users to queue.';
}

/**
 * Update path alias for EU OSS catalogue.
 */
function joinup_core_deploy_109803(): void {
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
function joinup_core_deploy_109804(): void {
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
function joinup_core_deploy_109805(): void {
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
function joinup_core_deploy_109806(): void {
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
