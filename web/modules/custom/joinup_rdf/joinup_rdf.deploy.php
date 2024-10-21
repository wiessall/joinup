<?php

/**
 * @file
 * Deploy hooks for Joinup RDF module.
 */

declare(strict_types=1);

use Drupal\Core\Entity\Sql\SqlEntityStorageInterface;
use Drupal\joinup_rdf\MigrationHelper;

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
    if (empty($sandbox['solution_to_collection'][$uri])) {
      throw new \Exception("Collection not found for solution: $uri");
    }
    $migration_helper->migrateGroup('solution', $uri, $sandbox['solution_to_collection'][$uri]);
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

  // Fix the message__field_group_content.
  $query = $db->select('b_message__field_group_content', 'b');
  $results = $query
    ->fields('b', [])
    ->execute()
    ->fetchAll();

  // The field_group_content has been deleted in the config sync and a new field
  // was created called group_content.
  foreach ($results as $result) {
    if ($result->field_group_content_target_type === 'rdf_entity') {
      $result->field_group_content_target_id = $db->select('rdf_sync_uri', 'u')
        ->fields('u', ['entity_id'])
        ->condition('u.uri', $result->field_group_content_target_id)
        ->execute()
        ->fetchField();
    }
    $result->group_content_target_id = $result->field_group_content_target_id;
    unset($result->field_group_content_target_type);
    unset($result->field_group_content_target_id);
    unset($result->field_group_content_target_id_int);
  }

  // Convert all objects to array.
  $results = array_map('get_object_vars', $results);

  // Insert the updated entries in the new message__group_content table.
  foreach ($results as &$result) {
    $db->insert('message__group_content')
      ->fields(array_keys($result))
      ->values($result)
      ->execute();
  }
  $schema->dropTable('b_message__field_group_content');

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
    $db->query("UPDATE {menu_tree} SET discovered = 0 WHERE menu_name LIKE 'ogmenu-%'");
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
 * Embedded entity references.
 */
function joinup_rdf_deploy_200008(?array &$sandbox = NULL): string {
  $db = \Drupal::database();
  if (!isset($sandbox['tables'])) {
    // First cleanup orphan revisions to lower the work with rich text blob
    // fields embed entity references.
    $tables = [
      'body',
      'field_attachment',
      'field_custom_page_logo',
      'field_document_publication_date',
      'field_document_spatial_coverage',
      'field_event_agenda',
      'field_event_contact_email',
      'field_event_logo',
      'field_event_online_location',
      'field_event_web_url',
      'field_file',
      'field_glossary_synonyms',
      'field_keywords',
      'field_licence',
      'field_location',
      'field_news_logo',
      'field_news_referenced_solution',
      'field_news_source_url',
      'field_news_spatial_coverage',
      'field_organisation_type',
      'field_paragraphs_body',
      'field_scope',
      'field_shared_in',
      'field_short_title',
      'field_topic',
      'owner_type',
    ];
    foreach ($tables as $table) {
      $db->query("DELETE r FROM {node_revision__{$table}} r LEFT JOIN {node__{$table}} n ON r.entity_id = n.entity_id WHERE n.entity_id IS NULL");
    }

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
    $migrationHelper = \Drupal::getContainer()->get('joinup_rdf.migration_helper');
    $sandbox['uri_to_uuid'] = $migrationHelper->getUriToUuid();
    $sandbox['uri_to_nid'] = $migrationHelper->getUriToNid();
  }

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
      '@href="(?:https://joinup.ec.europa.eu)?/?rdf_entity/(?<uri>[^"/]*)(?<subpath>/.*)?"@' => static function (array $matches) use ($sandbox): string {
        $uri = strtr($matches['uri'], array_reverse(array_flip(MigrationHelper::URL_ENCODE_MAP), TRUE));
        if (!$nid = $sandbox['uri_to_nid'][$uri] ?? NULL) {
          // Remove the URL if it doesn't point to valid content.
          return 'href=""';
        }
        $subPath = $matches['subpath'] ?? '';
        return 'href="' . base_path() . 'node/' . $nid . $subPath . '"';
      },
    ];

    $value = preg_replace_callback_array($callbacks, $value);
    if ($value !== $original) {
      $db->update($table)->fields([$updatedField => $value])->condition($idField, $id)->execute();
    }
  }

  $sandbox['#finished'] = (int) empty($sandbox['tables']);
  return "Processed table $table (field $updatedField)";
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
 * Fix EU OSS Catalogue menu link.
 */
function joinup_rdf_deploy_200010(): void {
  \Drupal::getContainer()->get('entity.repository')
    ->loadEntityByUuid('menu_link_content', '686904d4-8e5f-4dfd-baea-e4e6c6496984')
    ->save();
}

/**
 * Filters in content listings.
 */
function joinup_rdf_deploy_200011(?array &$sandbox = NULL): void {
  if (!isset($sandbox['tables'])) {
    $sandbox['tables'] = [];

    $fieldManager = \Drupal::getContainer()->get('entity_field.manager');
    $entityTypeManager = \Drupal::entityTypeManager();

    // Iterate over all search_api_field fields. Collect tables using them.
    foreach ($fieldManager->getFieldMapByFieldType('search_api_field') as $entityTypeId => $fields) {
      $storage = $entityTypeManager->getStorage($entityTypeId);
      assert($storage instanceof SqlEntityStorageInterface);

      $fieldStorageDefinitions = $fieldManager->getFieldStorageDefinitions($entityTypeId);
      $tableMapping = $storage->getTableMapping();

      foreach ($fields as $fieldName => $info) {
        foreach ($tableMapping->getAllFieldTableNames($fieldName) as $table) {
          $column = $tableMapping->getFieldColumnName($fieldStorageDefinitions[$fieldName], 'value');
          $sandbox['tables'][] = [$table, $column];
        }
      }
    }
    $sandbox['uri_to_nid'] = \Drupal::getContainer()->get('joinup_rdf.migration_helper')->getUriToNid();
  }

  // Converts the legacy RDF filter to a node filter. NULL to mark this filter
  // for removal because the target entity doesn't exist anymore.
  $fixFilter = static function (array $filter) use ($sandbox): ?array {
    if (str_starts_with($filter['plugin'], 'bundle_autocomplete:rdf_entity:')) {
      $filter['plugin'] = str_replace(':rdf_entity:', ':node:', $filter['plugin']);
      if (empty($sandbox['uri_to_nid'][$filter['target_id']])) {
        // Remove this filter as it points to an invalid entity.
        return NULL;
      }
      $filter['target_id'] = $sandbox['uri_to_nid'][$filter['target_id']];
      $filter['field'] = 'nid';
    }
    return $filter;
  };

  $db = \Drupal::database();
  $like = '%' . $db->escapeLike('bundle_autocomplete:rdf_entity:') . '%';

  [$table, $column] = array_shift($sandbox['tables']);

  $rows = $db->query("SELECT revision_id, $column FROM {{$table}} WHERE $column LIKE :like", [
    ':like' => $like,
  ], ['allow_delimiter_in_query' => TRUE])->fetchAllKeyed();

  foreach ($rows as $revisionId => $value) {
    $value = unserialize($value, ['allowed_classes' => FALSE]);
    $value['query_builder']['filters'] = array_values(
      array_filter(
        array_map($fixFilter, $value['query_builder']['filters'] ?? [])
      )
    );
    $db->query("UPDATE {{$table}} SET $column = :value WHERE revision_id = :revision_id", [
      ':value' => serialize($value),
      ':revision_id' => $revisionId,
    ]);
  }

  $sandbox['#finished'] = (int) empty($sandbox['tables']);
}

/**
 * Cleanup the simple sitemap calculated data.
 */
function joinup_rdf_deploy_200012(): void {
  // Sitemap keeps stashed and queued data that might contain data from the
  // removed "rdf_entity" entities.
  \Drupal::keyValue('simple_sitemap')->delete('queue_stashed_results');
  \Drupal::service('queue')->get('simple_sitemap_elements')->deleteQueue();
}

/**
 * Restore related solutions.
 */
function joinup_rdf_deploy_200013(): void {
  $data = \Drupal::state()->get('joinup_rdf.related_solutions', []);
  $database = \Drupal::database();
  $rdf_sync_mapper = \Drupal::getContainer()->get('rdf_sync.mapper');
  $meta_entity_storage = \Drupal::entityTypeManager()->getStorage('meta_entity');

  foreach ($data as $mid => $related_solutions) {
    $meta_entity = $meta_entity_storage->load($mid);
    $solution = $meta_entity->getTargetEntity();
    ksort($related_solutions);
    $target_ids = array_map(function ($uri) use ($rdf_sync_mapper) {
      $entity = $rdf_sync_mapper->getEntityByUri($uri);
      // Skip invalid references e.g.
      // http://data.europa.eu/w21/5e74061e-dc4e-4734-a31f-e328b7ab4326.
      if (!$entity) {
        return NULL;
      }
      return $entity->id();
    }, $related_solutions);

    // Filter out invalid entries and reset deltas.
    $target_ids = array_values(array_filter($target_ids));
    foreach ($target_ids as $delta => $target_id) {
      $values = [
        'bundle' => 'node',
        'deleted' => 0,
        'entity_id' => $solution->id(),
        'revision_id' => $solution->getRevisionId(),
        'langcode' => $solution->language()->getId(),
        'delta' => $delta,
        'related_solutions_target_id' => $target_id,
      ];

      foreach (['node__related_solutions', 'node_revision__related_solutions'] as $table) {
        $database->insert($table)
          ->fields(array_keys($values))
          ->values($values)
          ->execute();
      }
    }
  }

  \Drupal::state()->delete('joinup_rdf.related_solutions');
}

/**
 * Clear the purge_queue table of anything related to rdf_entity.
 */
function joinup_rdf_deploy_200014(): void {
  $data = \Drupal::database()->select('purge_queue', 'pq')
    ->fields('pq', ['item_id'])
    ->condition('pq.data', '%rdf_entity%', 'LIKE')
    ->execute()
    ->fetchCol();

  if ($data) {
    \Drupal::database()->delete('purge_queue')
      ->condition('item_id', $data, 'IN')
      ->execute();
  }
}
