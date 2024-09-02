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
    $migration_helper->migrateGroup('collection', $uri);
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
    $migration_helper->migrateGroup('solution', $uri, $collectionNid);
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
    'flag_counts' => 'entity_id',
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
    $db->query("DELETE FROM {menu_tree} WHERE mlid IN(9725, 9726, 10133, 10134, 10162, 10163, 14335, 14445, 14452, 19790, 19900, 19907)");
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
 * Fix redirects and paths.
 */
function joinup_rdf_deploy_200007(): void {
  $db = \Drupal::database();

  $patterns = [
    ['SUBSTRING(r.redirect_redirect__uri, 22)', 'internal:/rdf_entity/%', ''],
    ["TRIM(TRAILING '/releases' FROM SUBSTRING(r.redirect_redirect__uri, 22))", 'internal:/rdf_entity/%', '/releases'],
  ];
  foreach ($patterns as [$base, $like, $suffix]) {
    $expression = MigrationHelper::sqlExpressionUriDecode($base);
    $sql = <<<SQL
      UPDATE {redirect} r
      INNER JOIN {rdf_sync_uri} u ON $expression = u.uri
      SET r.redirect_redirect__uri = CONCAT(:prefix, u.entity_id, :suffix)
      WHERE r.redirect_redirect__uri LIKE :like
      SQL;
    $db->query($sql, [
      ':prefix' => 'internal:/node/',
      ':like' => $like,
      ':suffix' => $suffix,
    ], ['allow_delimiter_in_query' => TRUE]);
  }

  // Delete stale redirects.
  $toDelete = [
    'internal:/rdf_entity/http_e_f_fndl_cgo_cjp_fdcndl_fterms_gtype_vdistribution',
    'internal:/rdf_entity/http_e_f_fwww_cw3_corg_fTR_f2007_fWD_bxhtml_brdfa_bscenarios_b20070330_f_gtype_vdistribution',
    'internal:/rdf_entity/http_e_f_fid_cesd_corg_cuk_fFunctionList_f1_c00_crdf',
    'internal:/rdf_entity/http_e_f_ftools_cietf_corg_fhtml_frfc4518',
    'internal:/rdf_entity/http_e_f_feur_blex_ceuropa_ceu_fLexUriServ_fLexUriServ_cdo_guri_vOJ_eC_e2010_e083_e0329_e0334_een_ePDF',
    'internal:/rdf_entity/http_e_f_fwww_cw3_corg_fTR_f2013_fNOTE_brdf_bjson_b20131107_f_gtype_vdistribution',
    'internal:/rdf_entity/http_e_f_fwww_cyap_cgov_cgr_fe_bgif_fschemas_fcurrent_fBusinessInformationEntities_feGIF_aTravellerPerson_aABIE_bv1_b1_cxsd',
    'internal:/rdf_entity/http_e_f_fdata_ceuropa_ceu_fw21_f28879981_b50a6_b4f9b_bbdb6_ba31d85e19393',
    'internal:/rdf_entity/http_e_f_fdata_ceuropa_ceu_fw21_fc03f8496_bc80c_b4308_ba23d_bc77f6b7f55c2',
    'internal:/rdf_entity/http_e_f_fdata_ceuropa_ceu_fw21_f73995620_b6076_b4c3c_b8291_bab079293b2e3',
    'internal:/rdf_entity/http_e_f_fdata_ceuropa_ceu_fw21_f8605058e_be9d7_b4dee_bb5bd_bed0464bacb4b',
    'internal:/rdf_entity/http_e_f_fdata_ceuropa_ceu_fw21_fba59083a_b3283_b4285_bbced_ba243d80839b1',
    'internal:/rdf_entity/http_e_f_fdata_ceuropa_ceu_fw21_fb8a02cb3_bc36d_b4f28_b991d_b75a53432d082',
    'internal:/rdf_entity/http_e_f_fec_ceuropa_ceu_fleos_fprototype_fv3_c1_c1_fzip',
    'internal:/rdf_entity/http_e_f_fwww_cxrepository_cde_fns_fadms_fPublisher_fStatistischesBundesamt',
    'internal:/rdf_entity/http_e_f_fwww_cxrepository_cde_fns_fadms_fPublisher_fNieders_xC3_xA4chsischesMinisteriumf_xC3_xBCrWirtschaftArbeitundVerkehr',
    'internal:/rdf_entity/http_e_f_fwww_cxrepository_cde_fns_fadms_fPublisher_fKoopAADV',
    'internal:/rdf_entity/http_e_f_fwww_cxrepository_cde_fns_fadms_fPublisher_fArbeitskreisIderInnenministerkonferenz',
    'internal:/rdf_entity/http_e_f_fwww_cxrepository_cde_fns_fadms_fPublisher_fDieInnenministervonBundundL_xC3_xA4ndern',
    'internal:/rdf_entity/http_e_f_fwww_cxrepository_cde_fns_fadms_fPublisher_fAGVEMAGS_x2816Bundesl_xC3_xA4nder_x2BBunddurchVerwaltungsvereinbarungVEMAGS_x29',
    'internal:/rdf_entity/http_e_f_fwww_cxrepository_cde_fns_fadms_fPublisher_fBundesministeriumdesInnernReferatKM5',
    'internal:/rdf_entity/http_e_f_fwww_cxrepository_cde_fns_fadms_fPublisher_fFederalStatisticalOfficeofGermany_x28Destatis_x29Wiesbaden',
    'internal:/rdf_entity/http_e_f_fwww_cxrepository_cde_fns_fadms_fPublisher_fBundesministeriumdesInneren',
    'internal:/rdf_entity/http_e_f_fwww_cxrepository_cde_fns_fadms_fPublisher_fBundesministeriumdesInnerenimAuftragderBundL_xC3_xA4nderArbeitsgruppeNWRVorhabenDeutschlandOnlineNationalesWaffenregister',
    'internal:/rdf_entity/http_e_f_fwww_cxrepository_cde_fns_fadms_fPublisher_fBundesvereinigungderKommunalenSpitzenverb_xC3_xA4nde_x2FFederalMinistryoftheInterior',
    'internal:/rdf_entity/http_e_f_fwww_cxrepository_cde_fns_fadms_fPublisher_fFederalOfficeofJustice',
    'internal:/rdf_entity/http_e_f_fwww_cxrepository_cde_fns_fadms_fPublisher_fFederalInstituteofHydrology',
    'internal:/rdf_entity/http_e_f_fwww_cxrepository_cde_fns_fadms_fPublisher_fX_xC3_x96V_bKoordination',
    'internal:/rdf_entity/http_e_f_fwww_cxrepository_cde_fns_fadms_fPublisher_fBund_bL_xC3_xA4nderkommissionf_xC3_xBCrDatenverarbeitungundRationalisierunginderJustiz_x28BLK_x29',
    'internal:/rdf_entity/http_e_f_fdigst_cdk',
    'internal:/rdf_entity/http_e_f_fadministracionelectronica_cgob_ces_fctt_fpublisher_fE04921301',
    'internal:/rdf_entity/http_e_f_fdata_ceuropa_ceu_fw21_fa92d78c7_b2fad_b4c81_ba402_be6338b565c37',
    'internal:/rdf_entity/http_e_f_fwww_cengisis_ccom_f',
    'internal:/rdf_entity/http_e_f_fwww_ccen_ceu_fwork_fareas_fict_febusiness_fpages_fws_bgitb_caspx',
    'internal:/rdf_entity/http_e_f_fadministracionelectronica_cgob_ces_fctt_fpublisher_fE04921801',
    'internal:/rdf_entity/http_e_f_fadministracionelectronica_cgob_ces_fctt_fpublisher_fE04921701',
    'internal:/rdf_entity/http_e_f_fadministracionelectronica_cgob_ces_fctt_fpublisher_fU00000031',
    'internal:/rdf_entity/http_e_f_fadministracionelectronica_cgob_ces_fctt_fpublisher_fA15002917',
    'internal:/rdf_entity/http_e_f_fdata_ceuropa_ceu_fw21_ff734ba46_b39ad_b4ee3_b92d9_b0417939a3c57',
    'internal:/rdf_entity/http_e_f_fdata_ceuropa_ceu_fw21_fc98bcbb9_b48b3_b4f3d_bbab5_b2d74c0a7d290',
    'internal:/rdf_entity/http_e_f_fdata_ceuropa_ceu_fw21_f5849022d_baf10_b4840_b97b1_b44644f261790/releases',
    'internal:/rdf_entity/http_e_f_fdata_ceuropa_ceu_fw21_f5849022d_baf10_b4840_b97b1_b44644f261790',
    'internal:/rdf_entity/http_e_f_fdata_ceuropa_ceu_fw21_f897bf318_b7b14_b4076_ba6e3_b0c5bdcde8a6b/releases',
    'internal:/rdf_entity/http_e_f_fdata_ceuropa_ceu_fw21_f897bf318_b7b14_b4076_ba6e3_b0c5bdcde8a6b',
    'internal:/rdf_entity/http_e_f_fdata_ceuropa_ceu_fw21_f40efb363_b12b5_b4220_b83b3_b51214cbec99c/releases',
    'internal:/rdf_entity/http_e_f_fdata_ceuropa_ceu_fw21_f40efb363_b12b5_b4220_b83b3_b51214cbec99c',
    'internal:/rdf_entity/http_e_f_fdata_ceuropa_ceu_fw21_f90880605_b4f93_b4e02_b8aa7_b250c11432d7a/releases',
    'internal:/rdf_entity/http_e_f_fdata_ceuropa_ceu_fw21_f90880605_b4f93_b4e02_b8aa7_b250c11432d7a',
    'internal:/rdf_entity/http_e_f_fdata_ceuropa_ceu_fw21_f465b11f8_b1dc6_b49e9_b90b1_b2c4912f6296f',
    'internal:/rdf_entity/http_e_f_fdata_ceuropa_ceu_fw21_fa0f8175e_b7f9f_b4c8e_b9bfe_b212da0371273',
    'internal:/rdf_entity/https_e_f_fwww_cxrepository_cdeutschland_bonline_cde_fDatei_furn_euuid_e9601829d_b6aa2_b4aa4_ba2e3_b975fa07ab459_cxsd',
    'internal:/rdf_entity/https_e_f_fdigitaliser_cdk_f',
  ];
  $db->delete('redirect')
    ->condition('redirect_redirect__uri', $toDelete, 'IN')
    ->execute();

  // Delete stale pathauto key_value entries.
  $db->delete('key_value')
    ->condition('collection', 'pathauto_state.rdf_entity')
    ->execute();

  // Stale path alias entries.
  $ids = [
    19326,
    19828,
    19829,
    19836,
    20819,
    20854,
    20920,
    20921,
    20923,
    21164,
    21165,
    21202,
    21210,
    21466,
    21495,
    21681,
    21685,
    21701,
    21749,
    22536,
    24841,
    24842,
    24843,
    24844,
    24845,
    24846,
    24847,
    24848,
    28783,
    47350,
    47908,
    47909,
    47910,
    47911,
    47912,
  ];
  $db->delete('path_alias')->condition('id', $ids, 'IN')->execute();
  $db->delete('path_alias_revision')->condition('id', $ids, 'IN')->execute();
}

/**
 * Embedded entities.
 */
function joinup_rdf_deploy_200008(?array &$sandbox = NULL): string {
  $db = \Drupal::database();
  if (!isset($sandbox['tables'])) {
    $sandbox['tables'] = [
       ['comment__field_body', 'entity_id', 'field_body_value'],
       ['meta_entity__eif_description', 'entity_id', 'eif_description_value'],
       ['node__body', 'entity_id', 'body_value'],
       ['node_revision__body', 'entity_id', 'body_value'],
       ['node__description', 'entity_id', 'description_value'],
       ['node_revision__description', 'entity_id', 'description_value'],
       ['node__field_event_agenda', 'entity_id', 'field_event_agenda_value'],
       ['node_revision__field_event_agenda', 'entity_id', 'field_event_agenda_value'],
       ['paragraph__field_body', 'entity_id', 'field_body_value'],
       ['paragraph_revision__field_body', 'entity_id', 'field_body_value'],
       ['taxonomy_term_field_data', 'tid', 'description__value'],
       ['taxonomy_term_field_revision', 'tid', 'description__value'],
    ];
    $query = $db->select('rdf_sync_uri', 'u')
      ->fields('u', ['uri'])
      ->condition('entity_type', 'node');
    $query->innerJoin('node', 'n', 'n.nid = u.entity_id');
    $sandbox['uri_to_uuid'] = $query->fields('n', ['uuid'])
      ->execute()
      ->fetchAllKeyed();
    $sandbox['uri_to_nid'] = $db->select('rdf_sync_uri')
      ->fields('rdf_sync_uri', ['uri', 'entity_id'])
      ->condition('entity_type', 'node')
      ->execute()
      ->fetchAllKeyed();
  }

  $ids = [];
  [$table, $idField, $updatedField] = array_shift($sandbox['tables']);
  $rows = $db->select($table)
    ->fields($table, [$idField, $updatedField])
    ->condition($updatedField, '%rdf_entity%', 'LIKE')
    ->execute()
    ->fetchAllKeyed();
  foreach ($rows as $id => $value) {
    $original = $value;

    $dataEntityCallback = static function (array $matches) use ($sandbox): string {
      if (!$uuid = $sandbox['uri_to_uuid'][$matches['uri']] ?? NULL) {
        // Remove the attributes if they don't refer a valid entity.
        return '';
      }
      return 'data-entity-type="node" data-entity-uuid="' . $uuid . '"';
    };

    $callbacks = [
      '@data-entity-type="rdf_entity"\s+data-entity-uuid="(?<uri>[^\"]*)"@' => $dataEntityCallback,
      '@data-entity-uuid="(?<uri>[^\"]*)"\s+data-entity-type="rdf_entity"@' => $dataEntityCallback,
      '@href="(?:https://joinup.ec.europa.eu)?/?rdf_entity/(?<uri>[^\"]*)"@' => static function (array $matches) use ($sandbox): string {
        $uri = strtr($matches['uri'], array_reverse(array_flip(MigrationHelper::URL_ENCODE_MAP), TRUE));
        if (!$nid = $sandbox['uri_to_nid'][$uri] ?? NULL) {
          // Remove the attribute if it doesn't point to valid content.
          return 'href=""';
        }
        return 'href="/node/' . $nid . '"';
      },
    ];

    $value = preg_replace_callback_array($callbacks, $value);
    if ($value !== $original) {
      $ids[] = $id;
      $db->update($table)->fields([$updatedField => $value])->condition($idField, $id)->execute();
    }
  }

  $sandbox['#finished'] = (int) empty($sandbox['tables']);
  return "Processed table $table (field $updatedField). Affected IDs: " . implode(', ', $ids);
}

/**
 * Meta entity fix.
 */
function joinup_rdf_deploy_200009(): void {
  $db = \Drupal::database();

  // Clear stale data.
  $db->delete('meta_entity')->condition('id', 5609)->execute();
  $db->delete('meta_entity__count')->condition('entity_id', 5609)->execute();

  // Fix labels referring RDF entities.
  $sql = <<<SQL
    UPDATE {meta_entity} m
    INNER JOIN {rdf_sync_uri} u ON SUBSTRING(m.label, 13) = u.uri AND u.entity_type = 'node'
    SET m.label = CONCAT('node: ', u.entity_id)
    WHERE m.label LIKE 'rdf_entity: %'
    SQL;
  $db->query($sql, [], ['allow_delimiter_in_query' => TRUE]);

  // Fill the integer version of ID.
  $db->query("UPDATE {meta_entity} SET target__target_id_int = target__target_id");

  // Fix 'rdf entity' ocuurrences in label.
  $db->query("UPDATE meta_entity SET label = REPLACE(label, 'rdf entity (', 'content item (')");
}

/**
 * Section moved from joinup_oss_catalogue.
 *
 * @todo Remove after v1.112.0 is released, in ISAICP-9045.
 */

/**
 * Import content for EU OpenSource Software Solutions Catalogue.
 */
function joinup_rdf_deploy_200010(): void {
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
function joinup_rdf_deploy_200011(array &$sandbox): string {
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
function joinup_rdf_deploy_200012(): void {
  $menuName = 'ogmenu-3728';
  $repository = \Drupal::getContainer()->get('entity.repository');

  $collection = $repository->loadEntityByUuid('node', OssCatalogueCollectionInterface::COLLECTION_UUID)
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

  /** @var \Drupal\menu_link_content\MenuLinkContentInterface $link */
  foreach ($menuLinkContentStorage->loadMultiple($ids) as $link) {
    $title = $link->getTitle();
    if ($title === 'Members') {
      $link->setUnpublished()->save();
    }
    elseif ($title === 'Welcome') {
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
