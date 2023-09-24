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

use Drupal\joinup_oss_catalogue\OssCatalogueCollectionInterface;

/**
 * Change the state name of all RDF entities from 'validated' to 'published'.
 */
function joinup_core_deploy_109700(): string {
  $database = \Drupal::getContainer()->get('sparql.endpoint');
  $workflow_urls = [
    'http://joinup.eu/ar/workflow_status' => [
      'http://joinup.eu/collection/published',
      'http://joinup.eu/collection/draft',
    ],
    'http://joinup.eu/contact_information/workflow_status' => [
      'http://joinup.eu/contact-information/published',
    ],
    'http://joinup.eu/is/workflow_status' => [
      'http://joinup.eu/solution/published',
      'http://joinup.eu/solution/draft',
    ],
    'http://joinup.eu/isr/workflow_status' => [
      'http://joinup.eu/asset_distribution/published',
      'http://joinup.eu/asset_distribution/draft',
    ],
    'http://joinup.eu/owner/workflow_status' => [
      'http://joinup.eu/owner/published',
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
 * Change the state name of all Node entities from 'validated' to 'published'.
 */
function joinup_core_deploy_109701(): string {
  \Drupal::getContainer()->get('database')->update('node__field_state')
    ->fields(['field_state_value' => 'published'])
    ->condition('field_state_value', 'validated')
    ->execute();

  // Take care of the revisions.
  \Drupal::getContainer()->get('database')->update('node_revision__field_state')
    ->fields(['field_state_value' => 'published'])
    ->condition('field_state_value', 'validated')
    ->execute();

  return 'Changed the state name of all nodes from "validated" to "published".';
}

/**
 * Dismantle EIF Recommendation view.
 */
function joinup_core_deploy_109702(): void {
  \Drupal::entityTypeManager()->getStorage('redirect')->load(125784)->delete();
}

/**
 * Update path alias for EU OSS catalogue.
 */
function joinup_core_deploy_109703(): void {
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
function joinup_core_deploy_109704(): void {
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
function joinup_core_deploy_109705(): void {
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
