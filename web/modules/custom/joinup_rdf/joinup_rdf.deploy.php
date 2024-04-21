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
