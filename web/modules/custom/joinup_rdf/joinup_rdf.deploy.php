<?php

/**
 * @file
 * Deploy hooks for Joinup RDF module.
 */

declare(strict_types=1);

use Drupal\Core\Serialization\Yaml;
use Drupal\joinup_taxonomy\Entity\MappedTerm;
use Drupal\pathauto\PathautoState;
use Drupal\search_api\Entity\Index;
use Drupal\sparql_entity_storage\UriEncoder;

/**
 * Disable RDF Sync for the deployment.
 */
function joinup_rdf_deploy_110900(): void {
  \Drupal::getContainer()->get('rdf_sync.synchronizer')->disableSynchronization();
}

/**
 * Restore distributions.
 */
function joinup_rdf_deploy_110901(array &$sandbox): string {
  $map = [
    'http://joinup.eu/ad/workflow_status' => 'field_state',
    'http://joinup.eu/rdf_entity/group' => 'og_audience',
    'http://purl.org/dc/terms/description' => 'description',
    'http://purl.org/dc/terms/format' => 'format',
    'http://purl.org/dc/terms/issued' => 'created',
    'http://purl.org/dc/terms/license' => 'field_licence',
    'http://purl.org/dc/terms/modified' => 'changed',
    'http://purl.org/dc/terms/title' => 'title',
    'http://www.w3.org/ns/adms#representationTechnique' => 'representation_technique',
    'http://www.w3.org/ns/adms#status' => 'process_status',
    'http://www.w3.org/ns/dcat#accessURL' => 'access_url',
    'https://schema.org/fileSize' => 'file_size',
  ];
  $storage = \Drupal::entityTypeManager()->getStorage('node');
  $logger = \Drupal::getContainer()->get('logger.factory')->get('joinup_rdf');
  $alias_repository = \Drupal::getContainer()->get('path_alias.repository');
  $connection = \Drupal::database();

  if (!isset($sandbox['files'])) {
    $fileSystem = \Drupal::getContainer()->get('file_system');
    $sandbox['files'] = array_keys($fileSystem->scanDirectory('private://distributions', '/\.yml$/'));
    $sandbox['progress'] = 0;
    $sandbox['swapped_aliases'] = 0;
  }

  $start = microtime(TRUE);
  $file = array_shift($sandbox['files']);
  $distributions = Yaml::decode(file_get_contents($file));
  $aliases_not_swapped = [];
  foreach ($distributions as $uri => $data) {
    $values = ['type' => 'distribution', 'uri' => $uri];
    foreach ($data as $predicate => $objects) {
      if (isset($map[$predicate])) {
        $fieldName = $map[$predicate];
        foreach ($objects as $object) {
          $value = $object['value'];
          // Referential integrity issue: The referred licence is missing. Avoid
          // data inconsistency.
          // @see https://citnet.tech.ec.europa.eu/CITnet/jira/browse/ISAICP-8649
          if ($fieldName === 'field_licence') {
            if (!MappedTerm::loadByUri($value)) {
              continue;
            }
          }
          $value = match ($fieldName) {
            'field_licence', 'format', 'representation_technique' => MappedTerm::loadByUri($value)->id(),
            'description' => ['value' => $value, 'format' => 'content_editor'],
            'changed', 'created' => strtotime($value),
            // Title can't be longer than 255 characters.
            'title' => substr($value, 0, 255),
            default => $value,
          };
          $values[$fieldName][] = $value;
        }
      }
    }

    if (empty($values['title'])) {
      $values['title'] = 'Untitled distribution';
      $logger->warning("Distribution with URI $uri has no title. Using 'Untitled distribution' instead. Distribution value array: @values", [
        '@values' => print_r($data, TRUE),
      ]);
    }

    // Set the OG reference as a parent if there is no parent found.
    $values['distribution_parent'] = _joinup_rdf_get_distribution_parent($uri);
    if (empty($values['distribution_parent'])) {
      assert(!empty($data['http://joinup.eu/rdf_entity/group'][0]['value']), "Distribution with URI $uri has no parent.");
      $values['distribution_parent'] = $data['http://joinup.eu/rdf_entity/group'][0]['value'] ?? NULL;
    }
    assert(!empty($values['distribution_parent']), "Distribution with URI $uri has no parent. Distribution value array: " . print_r($data, TRUE));
    $entity = $storage->create($values);

    $entity->skip_notification = TRUE;
    $entity->skip_parent_invalidation = TRUE;

    // We need to ensure that existing pathauto aliases persist.
    $pathauto_old_path = '/rdf_entity/' . UriEncoder::encodeUrl($uri);
    $has_alias = $alias_repository->lookupBySystemPath($pathauto_old_path, 'en');
    if ($has_alias) {
      $entity->get('path')->pathauto = PathautoState::SKIP;
    }

    $entity->save();
    if ($has_alias) {
      $internal_url = $entity->toUrl()->getInternalPath();
      $connection->update('path_alias')
        ->fields(['path' => $internal_url])
        ->condition('path', $pathauto_old_path)
        ->execute();

      $connection->update('path_alias_revision')
        ->fields(['path' => $internal_url])
        ->condition('path', $pathauto_old_path)
        ->execute();
      $sandbox['swapped_aliases']++;
    }

    $sandbox['progress']++;
  }

  $aliases_not_swapped = array_filter($aliases_not_swapped);
  $sandbox['swapped_aliases'] += count($aliases_not_swapped);
  $finish = microtime(TRUE);
  $count = count($distributions);
  $sandbox['#finished'] = (int) empty($sandbox['files']);
  $message = "Imported {$count} ({$sandbox['progress']} total) distributions in " . round($finish - $start, 2) . ' seconds. Aliases swapped: ' . $sandbox['swapped_aliases'];
  $message .= ' Aliases swapped: ' . implode(', ', $aliases_not_swapped);
  return $message;
}

/**
 * Helper function to determine a distribution's parent.
 *
 * @param string $distribution_id
 *   The distribution ID.
 *
 * @return string|null
 *   The parent ID or NULL if not found.
 */
function _joinup_rdf_get_distribution_parent(string $distribution_id): ?string {
  $query = <<<SPARQL
SELECT ?parent
WHERE {
  GRAPH ?graph {
    ?parent <http://www.w3.org/ns/dcat#distribution> <$distribution_id> .
  }
  VALUES ?graph {
    <http://joinup.eu/solution/published>
    <http://joinup.eu/solution/draft>
    <http://joinup.eu/asset_release/published>
    <http://joinup.eu/asset_release/draft>
  }
}
SPARQL;

  $results = \Drupal::getContainer()->get('sparql.endpoint')->query($query)->getArrayCopy();
  $parent = reset($results);
  if (!$parent) {
    return NULL;
  }

  return $parent->parent->getUri();
}

/**
 * Empty the purge queue.
 */
function joinup_rdf_deploy_110902(): string {
  // Purge queue reports more than 30.000 items post deploy stuck in queue which
  // is reaching a warning level amount of items. Purge the queue because we
  // anyway clear the cache during deploy.
  \Drupal::getContainer()->get('purge.queue')->emptyQueue();
  return 'Purge queue emptied';
}

/**
 * Implements entity definition updates.
 */
function joinup_rdf_deploy_110903(): string {
  $entity_update_manager = \Drupal::entityDefinitionUpdateManager();
  if (!$entity_update_manager->needsUpdates()) {
    return 'No updates needed';
  }

  $messages = [];
  $entity_field_manager = \Drupal::getContainer()->get('entity_field.manager');
  foreach ($entity_update_manager->getChangeList() as $entity_type_id => $changes) {
    $entity_type = $entity_update_manager->getEntityType($entity_type_id);
    if (isset($changes['field_storage_definitions'])) {
      $field_storage_definitions = $entity_field_manager->getFieldStorageDefinitions($entity_type_id);
      foreach ($changes['field_storage_definitions'] as $field_name => $value) {
        /** @var \Drupal\Core\Field\FieldStorageDefinitionInterface $storage_definition */
        $storage_definition = $field_storage_definitions[$field_name];
        $entity_update_manager->updateFieldStorageDefinition($storage_definition);
        $messages[] = "Updated field storage definition for $entity_type_id.$field_name";
      }
    }
    $entity_update_manager->updateEntityType($entity_type);
  }

  return implode("\n", $messages);
}

/**
 * Clean up asset distributions from the search index.
 */
function joinup_rdf_deploy_110904(): string {
  /** @var \Drupal\search_api\Entity\Index $index */
  $index = Index::load('published');
  $results = $index->query()->addCondition('entity_bundle', 'asset_distribution')->execute();
  $ids = array_keys($results->getResultItems());
  // ::trackItems{action} re-constructs the IDs so we need to remove the prefix
  // `entity:rdf_entity:` from the IDs.
  $ids = array_map(function (string $id): string {
    return str_replace('entity:rdf_entity/', '', $id);
  }, $ids);
  $index->trackItemsDeleted('entity:rdf_entity', $ids);

  return 'Asset distributions have been removed from the search index.';
}

/**
 * Re-enable RDF Sync.
 */
function joinup_rdf_deploy_110905(): void {
  \Drupal::getContainer()->get('rdf_sync.synchronizer')->enableSynchronization();
}
