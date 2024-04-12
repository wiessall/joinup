<?php

/**
 * @file
 * Deploy hooks for Joinup RDF module.
 */

declare(strict_types=1);

use Drupal\Core\Serialization\Yaml;
use Drupal\joinup_taxonomy\Entity\MappedTerm;
use Drupal\rdf_sync\RdfSyncConnection;

/**
 * Create open vocabularies terms.
 */
function joinup_rdf_deploy_110400(array &$sandbox): string {
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

  $rdfType = \Drupal::service('rdf_sync.mapper')->getBundleRdfType('taxonomy_term', $vid);
  $results = \Drupal::getContainer()->get('joinup_taxonomy.vocabulary_updater')
    ->registerGraph($graphUri, $graph)
    ->registerVocabulary($vid, $rdfType, $graphUri)
    ->update();

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
function joinup_rdf_deploy_110401(array &$sandbox): string {
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
      INNER JOIN {rdf_sync_uri} u ON n.$column = u.uri
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
function joinup_rdf_deploy_110402(array &$sandbox): string {
  $graphUris = [
    'http://adms-sw-v1.00',
    'http://adms_skos_v1.00',
    'http://countries-skos',
    'http://data-theme-skos',
    'http://data.europa.eu/w21/user-graph/e120079b-817b-4c4b-b27b-e97f25cc2c58',
    'http://data.europa.eu/w21/user-graph/ef5c2894-1fa1-43b8-870e-fe55f5e019ae',
    'http://eif_conceptual_model',
    'http://eif_interoperability_layer',
    'http://eif_principle',
    'http://eif_recommendation',
    'http://eif_solution_type',
    'http://eira_skos',
    'http://eira_skos_properties',
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

/**
 * Fully test the taxonomy terms conversion.
 */
function joinup_rdf_deploy_110403(): void {
  $expectedTerms = Yaml::decode(file_get_contents(__DIR__ . '/tests/fixtures/expected_terms.yml'));
  $vocabularyStorage = \Drupal::entityTypeManager()->getStorage('taxonomy_vocabulary');
  $termStorage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
  $fieldManager = \Drupal::getContainer()->get('entity_field.manager');
  $definitions = $fieldManager->getFieldStorageDefinitions('taxonomy_term');

  $errors = [];
  foreach ($vocabularyStorage->loadMultiple() as $vid => $vocabulary) {
    // Check that number of terms matches the expected.
    $tids = $termStorage->getQuery()->accessCheck(FALSE)->condition('vid', $vid)->execute();
    if (count($tids) !== count($expectedTerms['terms'][$vid])) {
      $errors[] = "Vocab $vid imported " . count($tids) . ' but expected ' . count($expectedTerms['terms'][$vid]);
      continue;
    }

    foreach (array_chunk($tids, 50) as $tidsToProcess) {
      /** @var \Drupal\joinup_taxonomy\Entity\MappedTerm $term */
      foreach ($termStorage->loadMultiple($tidsToProcess) as $tid => $term) {
        // Check that the URI has been created.
        if (!$uri = $term->getUri()) {
          $errors[] = "Term $vid:$tid doesn't have a URI";
          continue;
        }

        // Check that a term with this URI existed before.
        if (!isset($expectedTerms['terms'][$vid][$uri])) {
          $errors[] = "Term $vid:$tid:$uri doesn't exist";
          continue;
        }
        $expectedValues = $expectedTerms['terms'][$vid][$uri];

        // Check term name.
        if ($term->label() !== $expectedValues['name']) {
          $errors[] = "Term $vid:$tid name is '{$term->label()}' but '{$expectedValues['name']}' expected";
        }

        // Check weight.
        if ($term->getWeight() !== $expectedValues['weight']) {
          $errors[] = "Term $vid:$tid weight is {$term->getWeight()} but {$expectedValues['weight']} expected";
        }

        // Check description.
        if ($term->getDescription() != $expectedValues['description']) {
          $errors[] = "Term $vid:$tid description is:\n" . var_export($term->getDescription(), TRUE) . "\n but expected:\n" . var_export($expectedValues['description'], TRUE);
        }

        // Check parents.
        $expectedParent = $expectedValues['parent'] ? MappedTerm::loadByUri($expectedValues['parent'])->id() : '0';
        $actualParent = explode(', ', $term->get('parent')->getString());
        // Switched to multi-parent terms. In the legacy RDF taxonomy only a
        // parent was possible. Only check that the old parent is in list.
        if (!in_array($expectedParent, $actualParent, TRUE)) {
          $errors[] = "Term $vid:$tid expected parent '$expectedParent' not in actual '" . implode(', ', $actualParent) . "'";
        }

        // Check additional fields.
        foreach ($expectedTerms['def'][$vid] as $fieldName => $type) {
          if ($vid === 'file_type' && $fieldName === 'extension') {
            // The file type extension field is totally messed up in `develop`,
            // we're skipping from testing.
            continue;
          }

          $property = $definitions[$fieldName]->getMainPropertyName();
          $expectedValue = $expectedValues[$fieldName];
          $actualValue = [];
          /** @var \Drupal\Core\Field\FieldItemInterface $fieldItem */
          foreach ($term->get($fieldName) as $fieldItem) {
            $value = $fieldItem->getValue()[$property];
            $type = $property === 'target_id' ? 'int' : $type;
            $type && settype($value, $type);
            $actualValue[] = $value;
          }
          if ($property === 'target_id') {
            // Convert from URI to term ID for term references.
            $expectedValue = array_map(
              fn(string $item) => (int) MappedTerm::loadByUri($item)->id(),
              $expectedValue,
            );
            // Reference to itself has been possible in the legacy RDF taxonomy
            // but is no more accepted.
            $expectedValue = array_diff($expectedValue, [$tid]);
          }

          // Make the comparison possible.
          sort($expectedValue);
          sort($actualValue);

          if ($expectedValue != $actualValue) {
            $errors[] = "Term $vid:$tid field $fieldName is\n" . var_export($actualValue, TRUE) . "\nbut expected\n" . var_export($expectedValue, TRUE) . "\n";
          }
        }
      }
    }
  }
  if ($errors) {
    throw new \Exception("Errors:\n" . implode("\n", $errors));
  }
}
