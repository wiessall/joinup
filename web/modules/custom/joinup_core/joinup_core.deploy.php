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

declare(strict_types = 1);

use Drupal\media\Entity\MediaType;

/**
 * Clears TRR fields for potential cases.
 */
function joinup_core_deploy_0108700(array &$sandbox): void {
  $field_predicates = [
    'http://joinup.ec.europa.eu/trr#actor',
    'http://joinup.ec.europa.eu/trr#businessProcess',
    'http://joinup.ec.europa.eu/trr#productType',
    'http://joinup.ec.europa.eu/trr#StandardizationLevel',
    'http://joinup.eu/test_resource_type',
  ];
  $sparql = \Drupal::getContainer()->get('sparql.endpoint');
  $graphs = [
    '<http://joinup.eu/solution/published>',
    '<http://joinup.eu/solution/draft>',
  ];
  foreach ($graphs as $graph) {
    foreach ($field_predicates as $field_predicate) {
      $query = <<<QUERY
WITH {$graph}
DELETE { ?entity_id <{$field_predicate}> ?value }
WHERE {
    ?entity_id a ?type .
    FILTER NOT EXISTS {
      ?entity_id <http://purl.org/dc/terms/type> ?trr_value .
      VALUES ?trr_value { <http://data.europa.eu/dr8/ConformanceTestingComponent> <http://data.europa.eu/dr8/ConformanceTestingService> <http://data.europa.eu/dr8/ConformanceTestScenario> }
    }
    ?entity_id <{$field_predicate}> ?value
}
QUERY;
      $sparql->query($query);
    }
  }
}

/**
 * Queue contact information emails for validation.
 */
function joinup_core_deploy_108701(array &$sandbox): void {
  $queue = \Drupal::queue('joinup_email_confirmer');
  $queue->createQueue();

  /** @var \Drupal\sparql_entity_storage\Driver\Database\sparql\ConnectionInterface $sparql */
  $sparql = \Drupal::service('sparql.endpoint');
  $rows = $sparql->query('SELECT DISTINCT(?email)
    WHERE {
      GRAPH <http://joinup.eu/contact-information/published> {
        ?id <http://www.w3.org/2006/vcard/ns#hasEmail> ?email .
        ?id a <http://www.w3.org/2006/vcard/ns#Kind> .
      }
    }
    ORDER BY (?email)')->getArrayCopy();
  array_walk($rows, function (\stdClass $row) use ($queue): void {
    $queue->createItem(['email' => $row->email->getValue()]);
  });
}

/**
 * Migrate Video content into new Media field.
 */
function joinup_core_deploy_108702(array &$sandbox): string {
  if (empty($sandbox['ids'])) {
    $sandbox['ids'] = \Drupal::entityQuery('node')->condition('type', 'video')->execute();
    $sandbox['count'] = 0;
    $sandbox['max'] = count($sandbox['ids']);
  }
  $entity_type_manager = \Drupal::entityTypeManager();
  $entity_storage = $entity_type_manager->getStorage('node');
  $media_type = $entity_type_manager->getStorage('media_type')->loadByProperties(['source' => 'video_embed_field']);
  $media_type = reset($media_type);
  $nids = array_splice($sandbox['ids'], 0, 50);
  foreach ($entity_storage->loadMultiple($nids) as $node) {
    _joinup_core_deploy_migrate_field($media_type, 'video', 'node', (int) $node->id(), 'field_video');
    $sandbox['count']++;
  }

  $sandbox['#finished'] = $sandbox['count'] === $sandbox['max'];
  return "Migrate {$sandbox['count']} out of {$sandbox['max']} video embed field.";
}

/**
 * Migrate Video Embed Field to Media.
 *
 * @param \Drupal\media\Entity\MediaType $media_type
 *   Media type.
 * @param string $ref_field_name
 *   Field name.
 * @param string $entity_type
 *   Entity type.
 * @param int $id
 *   Entity id.
 * @param string $legacy_field
 *   Migration field.
 *
 * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
 * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
 * @throws \Drupal\Core\Entity\EntityStorageException
 */
function _joinup_core_deploy_migrate_field(MediaType $media_type, string $ref_field_name, string $entity_type, int $id, string $legacy_field): void {
  $entity_type_manager = \Drupal::entityTypeManager();
  $media_storage = $entity_type_manager->getStorage('media');
  $storage = $entity_type_manager->getStorage($entity_type);
  $source_field = $media_type->getSource()->getSourceFieldDefinition($media_type);
  $field_values = [];

  /** @var \Drupal\Core\Entity\ContentEntityBase $target_entity */
  $target_entity = $storage->load($id);
  $video_url = $target_entity->get($legacy_field)->value;
  if ($video_url) {
    $result = $media_storage->getQuery()
      ->condition('bundle', $media_type->id())
      ->condition($source_field->getName(), $video_url)
      ->execute();
    if (empty($result)) {
      $video_media = $media_storage->create([
        'bundle' => $media_type->id(),
        $source_field->getName() => [
          'value' => $video_url,
        ],
      ]);
      $video_media->setOwnerId($target_entity->getOwner()->id());
      $video_media->save();
    }
    else {
      $video_media = $media_storage->load(reset($result));
    }
    $field_values[] = ['target_id' => $video_media->id()];
    $target_entity->set($ref_field_name, $field_values);
    $target_entity->save();
  }
}
