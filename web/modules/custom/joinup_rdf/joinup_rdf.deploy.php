<?php

/**
 * @file
 * Deploy hooks for Joinup RDF module.
 */

declare(strict_types = 1);

use Drupal\Component\Serialization\Json;
use Drupal\joinup_taxonomy\VocabularyUpdater;
use Drupal\rdf_sync\RdfSyncConnection;
use EasyRdf\Graph;
use EasyRdf\Http;
use EasyRdf\Http\Client;
use EasyRdf\Http\Exception;
use EasyRdf\RdfNamespace;
use EasyRdf\Resource;

const JOINUP_RDF_VOCABS = [
  'http://actor' => ['actor'],
  'http://adms-sw-v1.00' => ['licence_type'],
  'http://adms_skos_v1.00' => ['owner_type', 'representation_technique', 'status'],
  'http://business-process' => ['business_process'],
  'http://countries-skos' => ['spatial'],
  'http://eif_conceptual_model' => ['eif_conceptual_model'],
  'http://eif_interoperability_layer' => ['eif_interoperability_layer'],
  'http://eif_principle' => ['eif_principle'],
  'http://eif_recommendation' => ['eif_recommendation'],
  'http://eif_solution_type' => ['eif_solution_type'],
  'http://eira_skos' => ['eira'],
  'http://filetypes-skos' => ['file_type'],
  'http://joinup.eu/license_type/published' => ['eurovoc'],
  'http://licence-legal-type' => ['legal_type'],
  'http://solution_category' => ['solution_category'],
  'http://standardization_level' => ['standardization_level'],
  'http://test_resource_type' => ['test_resource_type'],
  'http://topic' => ['topic'],
];

/**
 * Current vocabularies.
 */
function joinup_rdf_deploy_110200(array &$sandbox): string {
  if (!isset($sandbox['graphs'])) {
    $sandbox['graphs'] = JOINUP_RDF_VOCABS;
    $sandbox['callbacks'] = [
      'http://filetypes-skos' => [[VocabularyUpdater::class, 'alterGraphForFileType']],
    ];
    $sandbox['uri_to_tid'] = [];
  }

  $graphStoreHelper = \Drupal::getContainer()->get('joinup_sparql.graph_store.helper');
  $graphStore = $graphStoreHelper->createGraphStore();
  $graphUri = key($sandbox['graphs']);
  $vids = current($sandbox['graphs']);
  unset($sandbox['graphs'][$graphUri]);
  try {
    // Imports are often processing large amount of data. By default, the
    // EasyRdf HTTP Client timeout is set to 10 seconds only. Use a wider
    // interval to allow the import to succeed.
    $httpClient = (new Client())->setConfig([
      'maxredirects' => 5,
      'useragent' => 'EasyRdf HTTP Client',
      'timeout' => 60,
    ]);
    Http::setDefaultHttpClient($httpClient);
    $graph = $graphStore->get($graphUri);
  }
  catch (Exception $exception) {
    // 404 means no graph.
    if ($exception->getCode() === 404) {
      $sandbox['#finished'] = (int) empty($sandbox['graphs']);
      return sprintf('✅ %s: no terms', $graphUri);
    }
    throw $exception;
  }

  $mapper = \Drupal::getContainer()->get('rdf_sync.mapper');
  $vocabularyUpdater = \Drupal::getContainer()->get('joinup_taxonomy.vocabulary_updater');
  $vocabularyUpdater
    // Set URI <-> TID relations collected in previous batch operations.
    ->setUriToTid($sandbox['uri_to_tid'])
    ->registerGraph($graphUri, $graph, $sandbox['callbacks'][$graphUri] ?? []);
  foreach ($vids as $vid) {
    $rdfType = $mapper->getBundleRdfType('taxonomy_term', $vid);
    $vocabularyUpdater->registerVocabulary($vid, $rdfType, $graphUri);
  }

  $results = $vocabularyUpdater->update();

  // Save URI <-> TID relation in sandbox to serve dependency resolution when
  // terms depend on terms from other vocabularies.
  $sandbox['uri_to_tid'] = $vocabularyUpdater->getUriToTid();

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
 * Import terms from SPARQL backend (big vocabularies).
 */
function joinup_rdf_deploy_110201(array &$sandbox): string {
  // Graphs of 'language' and 'product_type' vocabularies are huge, and the
  // Virtuoso server's SPARQL query configuration is limited to manipulate such
  // large results. We'll import them directly from files.
  if (!isset($sandbox['vocabs'])) {
    $sandbox['vocabs'] = [
      'languages-skos.rdf' => 'language',
      'product_type.rdf' => 'product_type',
    ];
    $sandbox['callbacks'] = [
      'languages-skos.rdf' => [
        [VocabularyUpdater::class, 'prepareGraph'],
        [VocabularyUpdater::class, 'alterGraphForLanguage'],
      ],
      'product_type.rdf' => [
        [VocabularyUpdater::class, 'prepareGraph'],
      ],
    ];
  }

  $mapper = \Drupal::getContainer()->get('rdf_sync.mapper');
  $vocabularyUpdater = \Drupal::getContainer()->get('joinup_taxonomy.vocabulary_updater');

  $file = key($sandbox['vocabs']);
  $vid = current($sandbox['vocabs']);
  unset($sandbox['vocabs'][$file]);

  $graph = new Graph();
  $graph->parseFile(DRUPAL_ROOT . '/../resources/vocab/' . $file);
  $graphUri = 'http://' . strtolower(pathinfo($file, PATHINFO_FILENAME));
  $rdfType = $mapper->getBundleRdfType('taxonomy_term', $vid);
  $results = $vocabularyUpdater
    ->registerGraph($graphUri, $graph, $sandbox['callbacks'][$file])
    ->registerVocabulary($vid, $rdfType, $graphUri)
    ->update();

  $sandbox['#finished'] = (int) empty($sandbox['vocabs']);

  $stats = [];
  foreach ($results[$vid] as $op => $count) {
    $stats[] = "$count $op";
  }
  return sprintf('✅ %s: %s (%s)', $graphUri, $vid, implode(', ', $stats));
}

/**
 * Add EIRA extra-properties.
 */
function joinup_rdf_deploy_110202(array &$sandbox): void {
  RdfNamespace::set('eira', 'http://data.europa.eu/dr8/');
  // Replace the `dc` namespace with `dct` - requested by the client.
  RdfNamespace::delete('dc');
  RdfNamespace::delete('dcterms');
  RdfNamespace::set('dct', 'http://purl.org/dc/terms/');
  RdfNamespace::set('owlturtle', 'http://www.semanticweb.org/owl/owlapi/turtle#');

  $storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
  if (!isset($sandbox['tids'])) {
    $sandbox['tids'] = array_values(
      $storage->getQuery()->accessCheck(FALSE)->condition('vid', 'eira')->execute()
    );
  }

  $graph = \Drupal::getContainer()
    ->get('joinup_sparql.graph_store.helper')
    ->createGraphStore()->get('http://eira_skos_properties');
  $tids = array_splice($sandbox['tids'], 0, 50);
  foreach ($storage->loadMultiple($tids) as $term) {
    $resourceUri = $term->get('uri')->value . '_Properties';
    if (!empty($graph->toRdfPhp()[$resourceUri])) {
      $resource = $graph->resource($resourceUri);
      $namespaces = $rows = [];
      foreach ($resource->propertyUris() as $propertyUri) {
        $shortenedProperty = RdfNamespace::shorten($propertyUri);
        // Do not store the skos:topConceptOf and skos:related properties.
        if (in_array($shortenedProperty, ['skos:topConceptOf', 'skos:related'], TRUE)) {
          continue;
        }
        $prefix = RdfNamespace::prefixOfUri($propertyUri);
        $namespaces[$prefix] = [
          'namespace' => $prefix . ':',
          'uri' => RdfNamespace::get($prefix),
        ];
        foreach ($graph->all($resource, $shortenedProperty) as $object) {
          $value = $object instanceof Resource ? $object->getUri() : $object->getValue();
          if ($value) {
            $rows[] = [
              'property' => $shortenedProperty,
              'value' => $value,
            ];
          }
        }
      }
      $term->set('extra_properties', Json::encode([
        'namespaces' => $namespaces,
        'rows' => $rows,
      ]))->save();
    }
  }

  $sandbox['#finished'] = (int) empty($sandbox['tids']);
}

/**
 * Fix term reference fields.
 */
function joinup_rdf_deploy_110203(array &$sandbox): string {
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
function joinup_rdf_deploy_110204(array &$sandbox): string {
  $graphUris = [
    ...array_keys(JOINUP_RDF_VOCABS),
    ...['http://languages-skos', 'http://product_type'],
  ];
  sort($graphUris);
  foreach ($graphUris as $graphUri) {
    RdfSyncConnection::query("CLEAR GRAPH <$graphUri>;");
  }
  return dt("Removed graphs: !graphs", ['!graphs' => implode(', ', $graphUris)]);
}
