<?php

/**
 * @file
 * Deploy functions for Joinup.
 *
 * This should only contain update functions that rely on the Drupal API and
 * need to run _after_ the configuration is imported.
 *
 * This is applicable in most cases. However in case the update code enables
 * some functionality that is required for configuration to be successfully
 * imported, it should instead be placed in joinup_core.post_update.php.
 */

declare(strict_types = 1);

use Drupal\interoperable_europe\InteroperableEuropeCollectionInterface;

/**
 * Clears TRR fields for potential cases.
 */
function joinup_core_deploy_0108700(array &$sandbox): void {
  $field_predicates = [
    'http://joinup.ec.europa.eu/trr#actor',
    'http://joinup.ec.europa.eu/trr#businessProcess',
    'http://joinup.ec.europa.eu/trr#productType',
    'http://joinup.ec.europa.eu/trr#StandardizationLevel',
    'http://joinup.eu/test_resource_type',
  ];
  $sparql = \Drupal::getContainer()->get('sparql.endpoint');
  $graphs = [
    '<http://joinup.eu/solution/published>',
    '<http://joinup.eu/solution/draft>',
  ];
  foreach ($graphs as $graph) {
    foreach ($field_predicates as $field_predicate) {
      $query = <<<QUERY
WITH {$graph}
DELETE { ?entity_id <{$field_predicate}> ?value }
WHERE {
    ?entity_id a ?type .
    FILTER NOT EXISTS {
      ?entity_id <http://purl.org/dc/terms/type> ?trr_value .
      VALUES ?trr_value { <http://data.europa.eu/dr8/ConformanceTestingComponent> <http://data.europa.eu/dr8/ConformanceTestingService> <http://data.europa.eu/dr8/ConformanceTestScenario> }
    }
    ?entity_id <{$field_predicate}> ?value
}
QUERY;
      $sparql->query($query);
    }
  }
}


/**
 * Import new landing page.
 */
function joinup_core_deploy_0108701(): void {
  /** @var \Drupal\Core\Extension\ModuleInstallerInterface $module_installer */
  $module_installer = \Drupal::service('module_installer');
  /** @var \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository */
  $entity_repository = \Drupal::service('entity.repository');

  // Import new content.
  $module_installer->install(['default_content']);
  /** @var \Drupal\default_content\ImporterInterface $importer */
  \Drupal::service('default_content.importer')->importContent('interoperable_europe', TRUE);
  $module_installer->uninstall(['default_content']);

  // Disable auto-created left-side menu link.
  $landing_page = $entity_repository->loadEntityByUuid('node', '61e83391-9f96-4c9e-995d-9b22705b9f9f');
  $link_plugins = \Drupal::service('plugin.manager.menu.link')->loadLinksByRoute('entity.node.canonical', [
    'node' => $landing_page->id(),
  ], 'ogmenu-3600');
  /** @var \Drupal\menu_link_content\Plugin\Menu\MenuLinkContent $link_plugin */
  $link_plugin = reset($link_plugins);
  $entity_repository
    ->loadEntityByUuid('menu_link_content', $link_plugin->getDerivativeId())
    ->setUnpublished()
    ->save();

  // Remove old IOP landing page.
  \Drupal::entityTypeManager()->getStorage('node')->load(704740)->delete();
}

/**
 * Update path alias IOPE.
 */
function joinup_core_deploy_0108702(): void {
  /** @var \Drupal\collection\Entity\CollectionInterface $collection */
  $collection = \Drupal::entityTypeManager()
    ->getStorage('rdf_entity')
    ->load(InteroperableEuropeCollectionInterface::COLLECTION_ENTITY_ID);

  // Get all entities from IOPE.
  $content = $collection->getGroupContentIds();

  $collection->path = ['alias' => '/interoperable-europe', 'pathauto' => 0];
  $collection->save();

  // Update entities in IOPE group.
  foreach ($content as $type => $entities) {
    foreach ($entities as $ids) {
      foreach ($ids as $id) {
        $entity = \Drupal::entityTypeManager()->getStorage($type)->load($id);
        $entity->path = ['pathauto' => 1];
        $entity->save();
      }
    }
  }
}
