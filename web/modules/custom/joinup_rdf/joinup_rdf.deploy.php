<?php

/**
 * @file
 * Deploy hooks for Joinup RDF module.
 */

declare(strict_types=1);

use Drupal\Core\Url;
use Drupal\collection\Entity\CollectionInterface;
use Drupal\custom_page\Entity\CustomPageInterface;
use Drupal\joinup_oss_catalogue\OssCatalogueCollectionInterface;
use Drupal\joinup_rdf\MigrationHelper;
use Drupal\meta_entity\Entity\MetaEntity;
use Drupal\og\OgGroupAudienceHelperInterface;

/**
 * Disable RDF Sync for the deployment.
 */
function joinup_rdf_deploy_200000(): void {
  \Drupal::getContainer()->get('rdf_sync.synchronizer')->disableSynchronization();
}

/**
 * Migrate collections.
 */
function joinup_rdf_deploy_200001(array &$sandbox): string {
  $endpoint = \Drupal::getContainer()->get('rdf_sync.connection');
  if (empty($sandbox['collections'])) {
    $query = <<<SPARQL
      SELECT DISTINCT ?id
      FROM <http://joinup.eu/collection/published>
      FROM <http://joinup.eu/collection/draft>
      WHERE {
        ?id a ?type .
      }
      SPARQL;
    $results = $endpoint->query($query)->getArrayCopy();
    $sandbox['collections'] = array_map(fn(\stdClass $result) => $result->id->getUri(), $results);
    $sandbox['progress'] = 0;
    $sandbox['total'] = count($sandbox['collections']);
  }

  $uris = array_splice($sandbox['collections'], 0, 50);
  $migration_helper = \Drupal::getContainer()->get('joinup_rdf.migration_helper');
  $start = microtime(TRUE);
  foreach ($uris as $uri) {
    $migration_helper->migrateCollection($uri);
    $sandbox['progress']++;
  }

  $finish = microtime(TRUE);
  $sandbox['#finished'] = $sandbox['progress'] / $sandbox['total'];
  return "Progress: {$sandbox['progress']}/{$sandbox['total']} in " . round($finish - $start, 2) . 's';
}

/**
 * Migrate solutions.
 */
function joinup_rdf_deploy_200002(array &$sandbox): string {
  $endpoint = \Drupal::getContainer()->get('rdf_sync.connection');
  if (empty($sandbox['solutions'])) {
    $collections = \Drupal::database()->select('rdf_sync_uri')
      ->fields('rdf_sync_uri', ['uri', 'entity_id'])
      ->condition('entity_type', 'node')
      ->condition('bundle', 'collection')
      ->execute()
      ->fetchAllKeyed();

    $query = <<<SPARQL
      SELECT ?collection ?solution
      FROM <http://joinup.eu/collection/published>
      FROM <http://joinup.eu/collection/draft>
      WHERE {
        ?collection <http://www.w3.org/ns/dcat#dataset> ?solution .
      }
      SPARQL;
    $results = $endpoint->query($query)->getArrayCopy();

    // Store the relation between solution URI and collection NID.
    $sandbox['solution_to_collection'] = [];
    foreach ($results as $row) {
      $sandbox['solution_to_collection'][$row->solution->getUri()] = (int) $collections[$row->collection->geturi()];
    }

    $query = <<<SPARQL
      SELECT DISTINCT ?id
      FROM <http://joinup.eu/solution/published>
      FROM <http://joinup.eu/solution/draft>
      WHERE {
        ?id a ?type .
      }
      SPARQL;
    $results = $endpoint->query($query)->getArrayCopy();
    $sandbox['solutions'] = array_map(fn(\stdClass $result) => $result->id->getUri(), $results);
    $sandbox['progress'] = 0;
    $sandbox['total'] = count($sandbox['solutions']);
  }

  $uris = array_splice($sandbox['solutions'], 0, 50);
  $migration_helper = \Drupal::getContainer()->get('joinup_rdf.migration_helper');
  $start = microtime(TRUE);
  foreach ($uris as $uri) {
    if (!$collectionNid ??= $sandbox['solution_to_collection'][$uri]) {
      throw new \Exception("Collection not found for solution: $uri");
    }
    $migration_helper->migrateSolution($uri, $collectionNid);
    $sandbox['progress']++;
  }

  $finish = microtime(TRUE);
  $sandbox['#finished'] = $sandbox['progress'] / $sandbox['total'];
  return "Progress: {$sandbox['progress']}/{$sandbox['total']} in " . round($finish - $start, 2) . 's';
}

/**
 * Clean the collection and solution graphs.
 */
function joinup_rdf_deploy_200003(): void {
  $endpoint = \Drupal::getContainer()->get('rdf_sync.connection');
  $endpoint->update('CLEAR GRAPH <http://joinup.eu/collection/published>');
  $endpoint->update('CLEAR GRAPH <http://joinup.eu/collection/draft>');
  $endpoint->update('CLEAR GRAPH <http://joinup.eu/solution/published>');
  $endpoint->update('CLEAR GRAPH <http://joinup.eu/solution/draft>');
}

/**
 * Restore group reference tables.
 */
function joinup_rdf_deploy_200004(): void {
  $db = \Drupal::database();
  $schema = $db->schema();
  foreach (MigrationHelper::GROUP_REF_TABLES as [$entityTypeId, $fieldName, $hasRevisions]) {
    $tables = ["{$entityTypeId}__$fieldName"];
    if ($hasRevisions) {
      $tables[] = "{$entityTypeId}_revision__$fieldName";
    }
    foreach ($tables as $table) {
      $query = $db->select("b_$table", 'b');
      $query->innerJoin('rdf_sync_uri', 'u', "b.{$fieldName}_target_id = u.uri");
      $query
        ->fields('b', MigrationHelper::DEDICATED_TABLE_COLUMNS)
        ->fields('u', ['entity_id']);
      $db->insert($table)
        ->fields([...MigrationHelper::DEDICATED_TABLE_COLUMNS, "{$fieldName}_target_id"])
        ->from($query)
        ->execute();
      $schema->dropTable("b_$table");
    }
  }

  // Joinup log table.
  $query = $db->select('b_joinup_log', 'b');
  $query->innerJoin('rdf_sync_uri', 'u', 'b.group = u.uri');
  $columns = [
    'id',
    'uid',
    'actor',
    'type',
    'message',
    'severity',
    'location',
    'referer',
    'hostname',
    'timestamp',
  ];
  $query
    ->fields('b', $columns)
    ->fields('u', ['entity_id']);
  $db->insert('joinup_log')
    ->fields([...$columns, 'group'])
    ->from($query)->execute();
  $schema->dropTable('b_joinup_log');

  // Change references from URI to integer for tables that didn't need field
  // storage definition update.
  $tables = [
    'file_usage' => 'id',
    'flagging' => 'entity_id',
    'invitation' => 'entity_id',
    'meta_entity' => 'target__target_id',
    'og_membership' => 'entity_id',
  ];
  foreach ($tables as $table => $field) {
    $db->query("UPDATE {{$table}} t INNER JOIN {rdf_sync_uri} u ON t.$field = u.uri SET t.$field = u.entity_id");
  }

  // Update subscription table.
  $db->query("UPDATE {og_membership__subscription_bundles} SET subscription_bundles_entity_type = 'node' WHERE subscription_bundles_entity_type = 'rdf_entity'");
  // Leftover from release conversion.
  $db->query("UPDATE {og_membership__subscription_bundles} SET subscription_bundles_bundle = 'release' WHERE subscription_bundles_bundle = 'asset_release'");
}

/**
 * Remove serialized entities from menu link content.
 */
function joinup_rdf_deploy_200005(?array &$sandbox = NULL): void {
  $tables = ['menu_link_content_data', 'menu_link_content_field_revision'];
  $db = \Drupal::database();
  $keys = ['entity_type' => FALSE, 'entity' => FALSE, 'language' => FALSE];

  if (!isset($sandbox['rows'])) {
    $sandbox['rows'] = [];
    foreach ($tables as $table) {
      // Cleanup stale links.
      $db->query("DELETE FROM {{$table}} WHERE id IN(9617, 9618, 10017, 10018, 10046, 10047, 14169, 14279, 14286, 19582, 19692, 19699)");

      // Get rows that potentially contain serialized entities.
      $sandbox['rows'] = [
        ...$sandbox['rows'],
        ...$db->query("SELECT '$table', id, link__options FROM {{$table}} WHERE link__options <> :empty", [':empty' => 'a:0:{}'])->fetchAll(\PDO::FETCH_NUM),
      ];
    }
    // Stale records in menu_tree table.
    $db->query("DELETE FROM {menu_tree} WHERE mlid IN(9725, 9726, 10133, 10134, 10162, 10163, 19790, 19900, 19907)");
  }

  $rows = array_splice($sandbox['rows'], 0, 250);
  foreach ($rows as [$table, $id, $options]) {
    $options = unserialize($options, ['allowed_classes' => FALSE]);
    if (array_intersect_key($options, $keys)) {
      $options = serialize(array_diff_key($options, $keys));
      $db->query("UPDATE {{$table}} SET link__options = :options WHERE id = :id", [':options' => $options, ':id' => $id]);
    }
  }

  $sandbox['#finished'] = (int) empty($sandbox['rows']);
}

/**
 * Fix the group menu links.
 */
function joinup_rdf_deploy_200006(?array &$sandbox = NULL): void {
  $db = \Drupal::database();
  $tables = ['menu_link_content_data', 'menu_link_content_field_revision'];

  if (!isset($sandbox['patterns'])) {
    $sandbox['patterns'] = [
      ['entity:node/', 'entity:rdf_entity/%', 19],
      [
        'route:view.discussions.overview;node=',
        'route:view.discussions.overview;rdf_entity=%',
        44,
      ],
      [
        'route:entity.node.glossary;node=',
        'route:entity.rdf_entity.glossary;letter=&rdf_entity=%',
        53,
      ],
      [
        'route:entity.node.glossary;node=',
        'route:entity.rdf_entity.glossary;rdf_entity=%',
        45,
      ],
      [
        'route:entity.node.member_overview;node=',
        'route:entity.rdf_entity.member_overview;rdf_entity=%',
        52,
      ],
      [
        'route:joinup_menu.website_link;node=',
        'route:joinup_menu.website_link;rdf_entity=%',
        43,
      ],
      [
        'route:joinup_release.solution_release.overview;node=',
        'route:asset_release.solution_asset_release.overview;rdf_entity=%',
        64,
      ],
      [
        'route:joinup_release.solution_release.overview;node=',
        'route:joinup_release.solution_release.overview;rdf_entity=%',
        59,
      ],
      [
        'route:entity.node.eif_perspective;node=',
        'route:entity.rdf_entity.eif_perspective;rdf_entity=%',
        52,
      ],
      [
        'route:joinup_menu.documentation_file;node=',
        'route:joinup_menu.documentation_file;rdf_entity=%',
        49,
      ],
    ];

    // Fix an inconsistency in some link URIs.
    foreach ($tables as $table) {
      $expression = MigrationHelper::sqlExpressionUriEncode("SUBSTRING(REPLACE(link__uri, '%3A', ':'), 52)");
      $sql = "UPDATE {{$table}} SET link__uri = CONCAT(:prefix, $expression) WHERE link__uri LIKE :like";
      $db->query($sql, [
        ':prefix' => 'route:entity.rdf_entity.eif_perspective;rdf_entity=',
        ':like' => 'route:entity.rdf_entity.eif_perspective;rdf_entity=%',
      ], ['allow_delimiter_in_query' => TRUE]);
    }
  }

  [$prefix, $like, $cutoff] = array_shift($sandbox['patterns']);
  foreach ($tables as $table) {
    $expression = MigrationHelper::sqlExpressionUriDecode("SUBSTRING(m.link__uri, $cutoff)");
    $setRediscover = $table === 'menu_link_content_data' ? ', m.rediscover = 1' : '';
    $sql = <<<SQL
      UPDATE {{$table}} m
      INNER JOIN {rdf_sync_uri} u ON $expression = u.uri
      SET m.link__uri = CONCAT('$prefix', u.entity_id)$setRediscover
      WHERE m.link__uri LIKE '$like'
      SQL;
    $db->query($sql, [], ['allow_delimiter_in_query' => TRUE]);
  }

  $sandbox['#finished'] = (int) empty($sandbox['patterns']);

  if ($sandbox['#finished']) {
    // Make sure we're picking up fresh values while rebuilding menu tree.
    \Drupal::entityTypeManager()->getStorage('menu_link_content')->resetCache();
    // Rebuild menu_tree table.
    \Drupal::getContainer()->get('plugin.manager.menu.link')->rebuild();
    // Restore 'rediscover' flag.
    $db->query("UPDATE {menu_link_content_data} SET rediscover = 0 WHERE menu_name LIKE 'ogmenu-%'");
  }
}

/**
 * Section moved from joinup_oss_catalogue.
 *
 * @todo Remove if joinup_oss_catalogue is deployed first.
 */

/**
 * Import content for EU OpenSource Software Solutions Catalogue.
 */
function joinup_rdf_deploy_200007(): void {
  $moduleInstaller = \Drupal::getContainer()->get('module_installer');
  $entityRepository = \Drupal::getContainer()->get('entity.repository');

  // Delete faulty meta entity.
  $entityRepository->loadEntityByUuid('meta_entity', 'ad9be48e-7116-44b3-8df2-db8cb9343268')?->delete();

  $moduleInstaller->install(['default_content']);
  \Drupal::service('default_content.importer')->importContent('joinup_rdf', TRUE);
  $moduleInstaller->uninstall(['default_content']);
}

/**
 * Make sure that oss_archived has a default value.
 */
function joinup_rdf_deploy_200008(array &$sandbox): string {
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
 * Fix references from/to EU OSS Catalogue landing page.
 */
function joinup_rdf_deploy_200009(): void {
  $menuName = 'ogmenu-3728';
  $mapper = \Drupal::getContainer()->get('rdf_sync.mapper');
  $repository = \Drupal::getContainer()->get('entity.repository');

  $collection = $mapper->getEntityByUri(OssCatalogueCollectionInterface::COLLECTION_ENTITY_ID)
    // Update path alias and label for EU OSS catalogue.
    ->set('path', ['alias' => '/eu-oss-catalogue', 'pathauto' => 0])
    ->set('title', 'EU Open Source Solutions Catalogue');
  assert($collection instanceof CollectionInterface);

  $landingPage = $repository->loadEntityByUuid('node', '54acbd6f-47f0-4280-ad09-0a07c397318b');
  assert($landingPage instanceof CustomPageInterface);
  $landingPage->set(OgGroupAudienceHelperInterface::DEFAULT_FIELD, $collection)->save();

  if (joinup_oss_catalogue_is_enabled()) {
    // Make EU OSS Catalogue public.
    $collection->set('field_state', 'published');
  }

  $collection->save();

  MetaEntity::loadOrCreate('collection_settings', $collection)
    ->set('label', 'EU OpenSource Software Solutions Catalogue node (Collection settings)')
    ->set('landing_page', $landingPage)
    ->save();

  // @see custom_page_node_insert()
  \Drupal::getContainer()->get('custom_page.og_menu_links_manager')
    ->addLink($landingPage, 'navigation', ['menu_name' => $menuName]);

  // Disable auto-created left-side menu link.
  $linkPlugins = \Drupal::service('plugin.manager.menu.link')
    ->loadLinksByRoute(
      'entity.node.canonical',
      ['node' => $landingPage->id()],
      $menuName,
    );

  /** @var \Drupal\menu_link_content\Plugin\Menu\MenuLinkContent $linkPlugin */
  $linkPlugin = reset($linkPlugins);

  $repository
    ->loadEntityByUuid('menu_link_content', $linkPlugin->getDerivativeId())
    ->setUnpublished()
    ->save();

  // Rename menu items for EU OSS catalogue.
  $menuLinkContentStorage = \Drupal::entityTypeManager()->getStorage('menu_link_content');

  // Collect the IDs of links to the custom page.
  $ids = $menuLinkContentStorage->getQuery()->accessCheck(FALSE)
    ->condition('bundle', 'menu_link_content')
    ->condition('menu_name', $menuName)
    ->execute();

  foreach ($menuLinkContentStorage->loadMultiple($ids) as $link) {
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
