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
use EasyRdf\Graph;

/**
 * Update path alias for EU OSS catalogue.
 */
function joinup_core_deploy_110400(): void {
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
function joinup_core_deploy_110401(): void {
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
function joinup_core_deploy_110402(): void {
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
function joinup_core_deploy_110403(): void {
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
function joinup_core_deploy_110404(array &$sandbox): string {
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
 * Restore missing data.
 */
function joinup_core_deploy_110405(): string {
  \Drupal::getContainer()->get('sparql.endpoint')->update('WITH <http://joinup.eu/solution/published> DELETE { ?s ?p ?o } WHERE { ?s ?p ?o . VALUES ?s { <http://data.europa.eu/w21/18a8c8c8-67da-4016-9cd9-aa24ba04f87c> } } ');
  $graph_store = \Drupal::getContainer()->get('joinup_sparql.graph_store.helper')->createGraphStore();
  $graph = new Graph();

  $data = <<<TURTLE
<http://data.europa.eu/w21/18a8c8c8-67da-4016-9cd9-aa24ba04f87c>	<http://joinup.eu/is/workflow_status>	"published"^^<http://www.w3.org/2001/XMLSchema#string> .
<http://data.europa.eu/w21/18a8c8c8-67da-4016-9cd9-aa24ba04f87c>	<http://www.w3.org/1999/02/22-rdf-syntax-ns#type>	<http://www.w3.org/ns/dcat#Dataset> .
<http://data.europa.eu/w21/18a8c8c8-67da-4016-9cd9-aa24ba04f87c>	<http://purl.org/dc/terms/modified>	"2024-03-12T15:49:48Z"^^<http://www.w3.org/2001/XMLSchema#dateTime> .
<http://data.europa.eu/w21/18a8c8c8-67da-4016-9cd9-aa24ba04f87c>	<http://joinup.eu/search>	"a:5:{s:6:\"fields\";a:4:{s:23:\"field_is_content_status\";a:2:{s:6:\"weight\";s:1:\"0\";s:6:\"region\";s:3:\"top\";}s:23:\"solution_content_bundle\";a:2:{s:6:\"weight\";s:1:\"0\";s:6:\"region\";s:3:\"top\";}s:25:\"solution_spatial_coverage\";a:2:{s:6:\"weight\";s:1:\"0\";s:6:\"region\";s:13:\"inline_facets\";}s:14:\"solution_topic\";a:2:{s:6:\"weight\";s:1:\"0\";s:6:\"region\";s:13:\"inline_facets\";}}s:7:\"enabled\";i:1;s:13:\"query_presets\";s:0:\"\";s:13:\"show_archived\";s:10:\"unarchived\";s:5:\"limit\";s:2:\"12\";}"^^<http://www.w3.org/2001/XMLSchema#string> .
<http://data.europa.eu/w21/18a8c8c8-67da-4016-9cd9-aa24ba04f87c>	<http://purl.org/dc/terms/abstract>	"This free, online course will provide you with advanced knowledge on the European Interoperability Reference Architecture." .
<http://data.europa.eu/w21/18a8c8c8-67da-4016-9cd9-aa24ba04f87c>	<http://www.w3.org/ns/dcat#contactPoint>	<http://data.europa.eu/w21/2c8ffb3b-ae81-4453-8dfe-3fc026a95234> .
<http://data.europa.eu/w21/18a8c8c8-67da-4016-9cd9-aa24ba04f87c>	<http://joinup.eu/language/default>	"1" .
<http://data.europa.eu/w21/18a8c8c8-67da-4016-9cd9-aa24ba04f87c>	<http://purl.org/dc/terms/title>	"European Interoperability Reference Architecture: a deep dive"@en .
<http://data.europa.eu/w21/18a8c8c8-67da-4016-9cd9-aa24ba04f87c>	<http://purl.org/dc/dcmitype/StillImage>	"624597"^^<http://www.w3.org/2001/XMLSchema#string> .
<http://data.europa.eu/w21/18a8c8c8-67da-4016-9cd9-aa24ba04f87c>	<http://purl.org/dc/terms/description>	"<p><strong>Click </strong><a href=\"https://academy.europa.eu/courses/the-european-interoperability-reference-architecture-a-deep-div\"><strong>here</strong></a><strong> to take the course on Europe Academy!</strong>&nbsp;</p><p><strong>Download the SCORM package on your eLearning platform.</strong></p><p>Interoperable Europe Academy is aiming to provide free of charge, high-quality online courses open to anyone. One of the Academy's main goals is reusability, that is why the content of all courses are made available for downloading and reusing.&nbsp;</p><p>All courses created by the Interoperable Europe Academy consist of a SCORM package, a description of the course in PDF format and the official logo.</p><p>All the information can be found in the table below.</p><table><tbody><tr><td>Description</td><td>Resources</td></tr><tr><td>Introduction to the European Interoperability Reference Architecture</td><td><a href=\"/sites/default/files/inline-files/EIRA%20-%20Deep%20Dive.zip\" data-entity-type=\"file\" data-entity-uuid=\"8a9ccf90-fe15-403b-85cd-81facd05cb9c\">EIRA - Deep Dive.zip</a></td></tr><tr><td>Quiz</td><td><a href=\"/sites/default/files/inline-files/EIRA%20Deep%20Dive%20-%20Quiz.zip\" data-entity-type=\"file\" data-entity-uuid=\"7176443c-10e3-4343-b8d9-025a0cb51fbb\">EIRA Deep Dive - Quiz.zip</a></td></tr><tr><td>Essential Course Information</td><td><a href=\"/sites/default/files/inline-files/EIRA%20Deep%20Dive%20-%20ECI.pdf\" data-entity-type=\"file\" data-entity-uuid=\"1564b5fd-4dce-4868-99bd-d0ca199397aa\">EIRA Deep Dive - ECI.pdf</a></td></tr><tr><td>Logo</td><td><a href=\"/sites/default/files/inline-files/IOP%20EU%20Academy%20for%20EU%20Academy%20Course%20Thumbnail%20600x%20383%20px%20EIRA.png\" data-entity-type=\"file\" data-entity-uuid=\"337405e4-7b15-451f-a8a2-fe37515f37eb\">IOP EU Academy for EU Academy Course Thumbnail 600x 383 px EIRA.png</a></td></tr></tbody></table><p>Should you need any additional information or clarification regarding the course, feel free to contact us at&nbsp;IOP-Academy at ec.europa.eu. We recommend you to contact us when you are setting up the course on your platform in order for us to provide you with all the information needed.</p><p>Please let us know when the course is available on your platform and enjoy the learning!</p>"@en .
<http://data.europa.eu/w21/18a8c8c8-67da-4016-9cd9-aa24ba04f87c>	<http://joinup.eu/language/translation_source>	"und"@en .
<http://data.europa.eu/w21/18a8c8c8-67da-4016-9cd9-aa24ba04f87c>	<http://purl.org/dc/terms/type>	<http://data.europa.eu/dr8/InteroperableDigitalPublicService> .
<http://data.europa.eu/w21/18a8c8c8-67da-4016-9cd9-aa24ba04f87c>	<http://joinup.eu/solution/webdav_creation>	"true"^^<http://www.w3.org/2001/XMLSchema#boolean> .
<http://data.europa.eu/w21/18a8c8c8-67da-4016-9cd9-aa24ba04f87c>	<http://joinup.eu/language/translation_outdated>	""@en .
<http://data.europa.eu/w21/18a8c8c8-67da-4016-9cd9-aa24ba04f87c>	<http://joinup.eu/solution/related_by_type>	"true"^^<http://www.w3.org/2001/XMLSchema#boolean> .
<http://data.europa.eu/w21/18a8c8c8-67da-4016-9cd9-aa24ba04f87c>	<http://joinup.eu/site-featured>	"false"^^<http://www.w3.org/2001/XMLSchema#boolean> .
<http://data.europa.eu/w21/18a8c8c8-67da-4016-9cd9-aa24ba04f87c>	<http://joinup.eu/language/translation_status>	"1"@en .
<http://data.europa.eu/w21/18a8c8c8-67da-4016-9cd9-aa24ba04f87c>	<http://purl.org/dc/terms/issued>	"2024-03-12T15:13:23Z"^^<http://www.w3.org/2001/XMLSchema#dateTime> .
<http://data.europa.eu/w21/18a8c8c8-67da-4016-9cd9-aa24ba04f87c>	<http://joinup.eu/language>	"en"@en .
<http://data.europa.eu/w21/18a8c8c8-67da-4016-9cd9-aa24ba04f87c>	<http://purl.org/dc/terms/alternative>	"iop-eiradive" .
<http://data.europa.eu/w21/18a8c8c8-67da-4016-9cd9-aa24ba04f87c>	<http://joinup.eu/owner/uid>	"749342"^^<http://www.w3.org/2001/XMLSchema#integer> .
<http://data.europa.eu/w21/18a8c8c8-67da-4016-9cd9-aa24ba04f87c>	<http://joinup.eu/solution/content_creation>	"facilitators_and_authors" .
<http://data.europa.eu/w21/18a8c8c8-67da-4016-9cd9-aa24ba04f87c>	<http://joinup.eu/vocabulary/topic>	<http://joinup.eu/ontology/topic#culture-and-education> .
<http://data.europa.eu/w21/18a8c8c8-67da-4016-9cd9-aa24ba04f87c>	<http://joinup.eu/solution/moderation>	"false"^^<http://www.w3.org/2001/XMLSchema#boolean> .
<http://data.europa.eu/w21/18a8c8c8-67da-4016-9cd9-aa24ba04f87c>	<http://purl.org/dc/terms/publisher>	<http://data.europa.eu/w21/e005c095-3f37-4b20-9915-e95ce19e7a01> .
<http://data.europa.eu/w21/18a8c8c8-67da-4016-9cd9-aa24ba04f87c>	<http://joinup.eu/language/translation_author>	"749342"@en .
TURTLE;

  $graph->parse($data, 'turtle');
  $graph_store->insert($graph, 'http://joinup.eu/solution/published');
  return 'The solution with UUID 18a8c8c8-67da-4016-9cd9-aa24ba04f87c has been restored.';
}

/**
 * Cleans up obsolete EIRA terms.
 */
function joinup_core_deploy_110406(): string {
  $count_graph = <<<SPARQL
SELECT ?s ?deletedTermUri
WHERE {
  GRAPH ?g {
    ?s a <http://www.w3.org/ns/dcat#Dataset> .
    ?s <http://purl.org/dc/terms/type> ?deletedTermUri .
    FILTER NOT EXISTS {
      SELECT ?deletedTermUri
      WHERE {
        GRAPH <http://eira_skos> {
          ?deletedTermUri <http://www.w3.org/2004/02/skos/core#topConceptOf> <http://data.europa.eu/dr8> .
        }
      }
    }
  }
}
SPARQL;

  $sparql = \Drupal::getContainer()->get('sparql.endpoint');
  $count = $sparql->query($count_graph)->numRows();

  $query = <<<SPARQL
DELETE {
  GRAPH ?g {
    ?s <http://purl.org/dc/terms/type> ?deletedTermUri .
  }
}
WHERE {
  GRAPH ?g {
    ?s a <http://www.w3.org/ns/dcat#Dataset> .
    ?s <http://purl.org/dc/terms/type> ?deletedTermUri .
    FILTER NOT EXISTS {
      SELECT ?deletedTermUri
      WHERE {
        GRAPH <http://eira_skos> {
          ?deletedTermUri <http://www.w3.org/2004/02/skos/core#topConceptOf> <http://data.europa.eu/dr8> .
        }
      }
    }
  }
}
SPARQL;

  $result = $sparql->update($query)->current();
  $message = $result->{"callret-0"}->getValue();
  \assert(str_contains($message, (string) $count), 'The number of deleted entries should match the number of entries to clean.');
  \assert($sparql->query($count_graph)->numRows() === 0, 'There should be no more entries to clean.');

  return 'Entries to clean: ' . $count . '. Result: ' . $message . '.';
}
