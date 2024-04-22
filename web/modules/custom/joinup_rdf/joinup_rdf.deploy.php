<?php

/**
 * @file
 * Deploy hooks for Joinup RDF module.
 */

declare(strict_types=1);

use Drupal\Component\Serialization\Json;
use Drupal\joinup_taxonomy\Entity\MappedTerm;
use EasyRdf\Graph;

/**
 * Import SPDX licences.
 */
function joinup_rdf_deploy_110500(): void {
  $graph = new Graph();
  $graph->parseFile(DRUPAL_ROOT . '/../resources/vocab/licenses.rdf');
  \Drupal::getContainer()->get('joinup_taxonomy.vocabulary_updater')
    ->registerGraph('http://licence', $graph)
    ->registerVocabulary('spdx_licence', 'http://spdx.org/rdf/terms#License', 'http://licence')
    ->update();
}

/**
 * Migrate licences to nodes.
 */
function joinup_rdf_deploy_110501(array &$sandbox): string {
  $map = [
    'http://joinup.eu/licence/deprecated' => 'deprecated',
    'http://joinup.eu/licence/legal-type' => 'legal_type',
    'http://purl.org/dc/terms/description' => 'description',
    'http://purl.org/dc/terms/type' => 'licence_type',
    'http://www.w3.org/2000/01/rdf-schema#label' => 'title',
    'http://www.w3.org/2002/07/owl#sameAs' => 'spdx_licence',
  ];
  $refs = ['legal_type', 'licence_type', 'spdx_licence'];
  $storage = \Drupal::entityTypeManager()->getStorage('node');

  if (!isset($sandbox['graph'])) {
    $sandbox['graph'] = Json::decode(file_get_contents('private://licences'));
    \Drupal::getContainer()->get('file_system')->delete('private://licences');
    $sandbox['progress'] = 0;
    $sandbox['total'] = count($sandbox['graph']);
  }

  $count = 0;
  while ($sandbox['graph'] && $count < 25) {
    $uri = key($sandbox['graph']);
    $data = current($sandbox['graph']);
    unset($sandbox['graph'][$uri]);

    $values = ['type' => 'licence', 'uri' => $uri];
    foreach ($data as $predicate => $objects) {
      if (isset($map[$predicate])) {
        $fieldName = $map[$predicate];
        foreach ($objects as $object) {
          $value = $object['value'];
          if (in_array($fieldName, $refs, TRUE)) {
            // Term reference.
            $value = MappedTerm::loadByUri($value)->id();
          }
          if ($fieldName === 'description') {
            // Formatted text needs a format.
            $value = ['value' => $value, 'format' => 'content_editor'];
          }
          if ($fieldName === 'deprecated') {
            // We need to convert that value from string to boolean.
            $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
          }
          $values[$fieldName][] = $value;
        }
      }
    }
    $storage->create($values)->save();

    $sandbox['progress']++;
    $count++;
  }

  $sandbox['#finished'] = (int) empty($sandbox['graph']);
  return "Imported {$sandbox['progress']} out of {$sandbox['total']} licences";
}

/**
 * Restore document > licence relation.
 */
function joinup_rdf_deploy_110502(array &$sandbox): string {
  $db = \Drupal::database();

  if (!isset($sandbox['document_to_licence'])) {
    $licenceUriToNid = \Drupal::database()->select('rdf_sync_uri')
      ->fields('rdf_sync_uri', ['uri', 'entity_id'])
      ->condition('entity_type', 'node')
      ->condition('bundle', 'licence')
      ->execute()
      ->fetchAllKeyed();
    $documentNidToLicenceUri = Json::decode(file_get_contents('private://document_to_licence'));
    \Drupal::getContainer()->get('file_system')->delete('private://document_to_licence');

    foreach ($documentNidToLicenceUri as $documentNid => $licenceUri) {
      if (isset($licenceUriToNid[$licenceUri])) {
        $sandbox['document_to_licence'][$documentNid] = $licenceUriToNid[$licenceUri];
      }
    }

    $sandbox['document_nid_to_vid'] = $db->select('node')
      ->fields('node', ['nid', 'vid'])
      ->condition('type', 'document')
      ->execute()
      ->fetchAllKeyed();

    $sandbox['progress'] = 0;
    $sandbox['total'] = count($sandbox['document_to_licence']);
  }

  $fields = ['bundle', 'entity_id', 'revision_id', 'langcode', 'delta', 'field_licence_target_id'];
  $insert = $db->insert('node__field_licence')->fields($fields);
  $insertRevision = $db->insert('node_revision__field_licence')->fields($fields);

  $count = 0;
  while ($sandbox['document_to_licence'] && $count < 50) {
    $documentNid = key($sandbox['document_to_licence']);
    $licenceNid = current($sandbox['document_to_licence']);
    unset($sandbox['document_to_licence'][$documentNid]);

    $values = [
      'bundle' => 'document',
      'entity_id' => $documentNid,
      'revision_id' => $sandbox['document_nid_to_vid'][$documentNid],
      'langcode' => 'en',
      'delta' => 0,
      'field_licence_target_id' => $licenceNid,
    ];
    $insert->values($values);
    $insertRevision->values($values);

    $sandbox['progress']++;
    $count++;
  }
  $insert->execute();
  $insertRevision->execute();

  $sandbox['#finished'] = (int) empty($sandbox['document_to_licence']);
  return "Converted {$sandbox['progress']} out of {$sandbox['total']} documents";
}

/**
 * Import EIF data.
 */
function joinup_rdf_deploy_110406(array &$sandbox): string {
  $map = [
    'http://data.europa.eu/eif/Layer' => 'eif_layer',
    'http://data.europa.eu/eif/Model' => 'eif_model',
    'http://data.europa.eu/eif/Principle' => 'eif_principle',
    'http://data.europa.eu/eif/Recommendation' => 'eif_recommendation',
    'http://joinup.eu/rdf_meta_entity/fields/type' => 'eif_solution_type',
    'http://purl.org/dc/terms/description' => 'eif_description',
  ];
  $refs = ['eif_layer', 'eif_principle', 'eif_model', 'eif_solution_type'];
  $storage = \Drupal::entityTypeManager()->getStorage('meta_entity');

  if (!isset($sandbox['eif'])) {
    $sandbox['eif'] = Yaml::decode(file_get_contents('private://eif.yml'));
    $sandbox['eif_wrapper'] = Yaml::decode(file_get_contents('private://eif_wrapper.yml'));
    $sandbox['total'] = count($sandbox['eif']);
    $sandbox['progress'] = 0;
  }

  $count = 0;
  while ($sandbox['eif'] && $count < 25) {
    $uri = key($sandbox['eif']);
    $data = current($sandbox['eif']);
    unset($sandbox['eif'][$uri]);

    $values = [
      'type' => 'eif_perspective',
      'target' => [
        'target_type' => 'rdf_entity',
        'target_id' => $data['http://joinup.eu/rdf_meta_entity/fields/target_id'][0]['value'],
      ],
      'uri' => $uri,
    ];
    foreach ($data as $predicate => $objects) {
      if (isset($map[$predicate])) {
        $fieldName = $map[$predicate];
        foreach ($objects as $object) {
          $value = $object['value'];
          if (in_array($fieldName, $refs, TRUE)) {
            // Term reference.
            $value = MappedTerm::loadByUri($value)->id();
          }
          if ($fieldName === 'eif_description') {
            // Formatted text needs a format.
            $value = ['value' => $value, 'format' => 'basic_html'];
          }
          // Recommendation values were stored in eif_wrapper entity types.
          if ($fieldName === 'eif_recommendation' && isset($sandbox['eif_wrapper'][$value])) {
            $targetUri = $sandbox['eif_wrapper'][$value]['http://data.europa.eu/eif/adms/qualifier'][0]['value'] ?? NULL;
            if (!$targetUri) {
              // This recommendation is missing mandatory term reference URI.
              continue;
            }
            $value = [
              'target_id' => MappedTerm::loadByUri($targetUri)->id(),
              'value' => $sandbox['eif_wrapper'][$value]['http://purl.org/dc/terms/description'][0]['value'] ?? '',
              'format' => 'basic_html',
            ];
          }
          $values[$fieldName][] = $value;
        }
      }
    }
    $storage->create($values)->save();

    $sandbox['progress']++;
    $count++;
  }

  $sandbox['#finished'] = (int) empty($sandbox['eif']);
  return "Processed {$sandbox['progress']} out of {$sandbox['total']} EIF items";
}

/**
 * Fully test the EIF data conversion.
 */
function joinup_rdf_deploy_110407(): void {
  $eif = Yaml::decode(file_get_contents('private://eif.yml'));
  $eifWrapper = Yaml::decode(file_get_contents('private://eif_wrapper.yml'));
  $descriptionPredicates = [
    'http://purl.org/dc/terms/description',
    'http://purl.org/dc/elements/1.1/description',
  ];
  $storage = \Drupal::entityTypeManager()->getStorage('meta_entity');
  $mapper = \Drupal::getContainer()->get('rdf_sync.mapper');
  $mappings = $mapper->getMappings(entityTypeId: 'meta_entity', bundle: 'eif_perspective');
  // Type is always 'eif_perspective'.
  unset($mappings['type']);

  /** @var \Drupal\eif\Entity\EifPerspectiveInterface[] $entities */
  $entities = $storage->loadByProperties(['type' => 'eif_perspective']);

  if (count($entities) !== count($eif)) {
    throw new \Exception(sprintf('Expected %s meta-entities, got %s', count($eif), count($entities)));
  }
  foreach ($entities as $id => $entity) {
    if (!$uri = $entity->getUri()) {
      throw new \Exception("Meta-entity with ID $id lacks a URI");
    }
    if (!isset($eif[$uri])) {
      throw new \Exception("Meta-entity with ID $id and URI $uri is not in the expected list");
    }

    foreach ($mappings as $fieldName => $columns) {
      $field = $entity->get($fieldName);
      foreach ($columns as $column => $mapping) {
        foreach ($field as $delta => $fieldItem) {
          $actualValue = $fieldItem->$column;
          if ($fieldName === 'eif_recommendation') {
            // Before update, this field did refer the eif_wrapper entities. We
            // have to extract values from $eifWrapper variable.
            $wrapper = $eifWrapper[$eif[$uri]['http://data.europa.eu/eif/Recommendation'][$delta]['value']];
            $qualifiedUri = $column === 'target_id' ? 'http://data.europa.eu/eif/adms/qualifier' : 'http://purl.org/dc/terms/description';
            $expectedValue = $wrapper[$qualifiedUri][0]['value'] ?? '';
          }
          else {
            $expectedValue = $eif[$uri][$mapping->predicate][$delta]['value'];
          }

          if (in_array($mapping->predicate, $descriptionPredicates, TRUE)) {
            // Apply processing to expected value to make comparison possible.
            $expectedValue = (string) check_markup($expectedValue, 'basic_html');
          }

          // Convert URI to TID, when appropriate.
          $expectedValue = $column === 'target_id' ? (int) MappedTerm::loadByUri($expectedValue)->id() : $expectedValue;
          if ($actualValue != $expectedValue) {
            throw new \Exception("Meta-entity with ID $id, field $fieldName, delta $delta, column $column expected '$expectedValue' got '$actualValue'");
          }
        }
      }
    }
  }

  $fileSystem = \Drupal::getContainer()->get('file_system');
  $fileSystem->unlink('private://eif.yml');
  $fileSystem->unlink('private://eif_wrapper.yml');
}
