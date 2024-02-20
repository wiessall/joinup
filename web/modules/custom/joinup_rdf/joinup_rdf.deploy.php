<?php

/**
 * @file
 * Deploy hooks for Joinup RDF module.
 */

declare(strict_types=1);

use Drupal\joinup_taxonomy\Drush\Commands\JoinupTaxonomyCommands;
use Drupal\rdf_sync\Model\SyncMode;
use Drupal\rdf_sync\RdfSyncConnection;
use EasyRdf\Http\Exception;

/**
 * Create locked vocabularies terms.
 */
function joinup_rdf_deploy_110300(): string {
  // Avoid RDF synchronization during deploy scripts. Synchronization is
  // performed after `drush deploy`.
  // @see .opts.yml
  \Drupal::getContainer()->get('rdf_sync.synchronizer')->setSyncMode(SyncMode::NONE);

  $mysql = sprintf(
    'mysql -u%s%s -h%s %s',
    getenv('DRUPAL_DATABASE_USERNAME'),
    getenv('DRUPAL_DATABASE_PASSWORD') ? ' -p' . getenv('DRUPAL_DATABASE_PASSWORD') : '',
    getenv('DRUPAL_DATABASE_HOST'),
    getenv('DRUPAL_DATABASE_NAME'),
  );
  exec('gunzip < ' . JoinupTaxonomyCommands::getDumpFilePath('sql') . ' | ' . $mysql);
  return '✅ created locked vocabularies terms from MySQL dump';
}

/**
 * Create open vocabularies terms.
 */
function joinup_rdf_deploy_110301(array &$sandbox): string {
  if (!isset($sandbox['graphs'])) {
    $sandbox['graphs'] = [
      'http://licence-legal-type' => 'legal_type',
      'http://topic' => 'topic',
    ];
  }

  $graphUri = key($sandbox['graphs']);
  $vid = current($sandbox['graphs']);
  unset($sandbox['graphs'][$graphUri]);
  try {
    $graph = RdfSyncConnection::createGraphStore()->get($graphUri);
  }
  catch (Exception $exception) {
    // 404 means no graph.
    if ($exception->getCode() === 404) {
      $sandbox['#finished'] = (int) empty($sandbox['graphs']);
      return sprintf('✅ %s: no terms', $graphUri);
    }
    throw $exception;
  }

  $vocabularyUpdater = \Drupal::getContainer()->get('joinup_taxonomy.vocabulary_updater');
  $vocabularyUpdater->registerGraph($graphUri, $graph);
  $rdfType = \Drupal::service('rdf_sync.mapper')->getBundleRdfType('taxonomy_term', $vid);
  $vocabularyUpdater->registerVocabulary($vid, $rdfType, $graphUri);

  $results = $vocabularyUpdater->update();

  $sandbox['#finished'] = (int) empty($sandbox['graphs']);

  // Build and return the message.
  $vidsMessage = [];
  foreach ($results as $vid => $result) {
    $stats = [];
    foreach ($result as $op => $count) {
      $stats[] = "$count $op";
    }
    $vidsMessage[] = sprintf('%s (%s)', $vid, implode(', ', $stats));
  }
  return sprintf('✅ %s: %s', $graphUri, implode(', ', $vidsMessage));
}

/**
 * Fix term reference fields.
 */
function joinup_rdf_deploy_110302(array &$sandbox): string {
  $db = \Drupal::database();
  $schema = $db->schema();

  if (!isset($sandbox['fields'])) {
    $sandbox['fields'] = [
      'field_document_spatial_coverage' => 'node',
      'field_event_spatial_coverage' => 'node',
      'field_news_spatial_coverage' => 'node',
      'field_topic' => 'node',
      'field_video_spatial_coverage' => 'node',
      'field_user_nationality' => 'user',
      'field_user_professional_domain' => 'user',
    ];
    $sandbox['table_prefixes'] = [
      'node' => ['node', 'node_revision'],
      'user' => ['user'],
    ];
  }

  // Extract a field from stack.
  $field = key($sandbox['fields']);
  $entityTypeId = current($sandbox['fields']);
  unset($sandbox['fields'][$field]);

  $store = \Drupal::keyValue('entity.storage_schema.sql');

  // This key-value needs update to align field storage definition.
  $key = "$entityTypeId.field_schema_data.$field";
  $definition = $store->get($key);

  foreach ($sandbox['table_prefixes'][$entityTypeId] as $tablePrefix) {
    $table = "{$tablePrefix}__$field";
    $column = "{$field}_target_id";

    // Replace the URI with TID in the 'target_id' field.
    $query = <<<QUERY
      UPDATE {{$table}} n
      INNER JOIN {taxonomy_term__uri} u ON n.$column = u.uri_value
      SET n.$column = u.entity_id
      QUERY;
    $db->query($query);

    // New field schema.
    $fieldSpec = [
      'type' => 'int',
      'unsigned' => TRUE,
      'not null' => TRUE,
      'description' => 'The ID of the target entity.',
    ];

    // Change the field type and recreate the index.
    $schema->changeField($table, $column, $column, $fieldSpec);
    $schema->dropIndex($table, $column);
    $schema->addIndex($table, $column, [$column], [
      'fields' => [$column => $fieldSpec],
      'indexes' => [$column => [$column]],
    ]);

    // Fix field storage definition.
    $definition[$table]['fields'][$column] = $fieldSpec;
  }
  $store->set($key, $definition);

  $sandbox['#finished'] = (int) empty($sandbox['fields']);

  return "✅ Fixed storage definition for field $entityTypeId.$field";
}

/**
 * Cleanup legacy data.
 */
function joinup_rdf_deploy_110303(array &$sandbox): string {
  $graphUris = [
    'http://adms-sw-v1.00',
    'http://adms_skos_v1.00',
    'http://countries-skos',
    'http://eif_conceptual_model',
    'http://eif_interoperability_layer',
    'http://eif_principle',
    'http://eif_recommendation',
    'http://eif_solution_type',
    'http://eira_skos',
    'http://filetypes-skos',
    'http://joinup.eu/license_type/published',
    'http://languages-skos',
    'http://licence-legal-type',
    'http://solution_category',
    'http://topic',
  ];
  foreach ($graphUris as $graphUri) {
    RdfSyncConnection::query("CLEAR GRAPH <$graphUri>;");
  }
  return dt("Removed graphs: !graphs", ['!graphs' => implode(', ', $graphUris)]);
}
