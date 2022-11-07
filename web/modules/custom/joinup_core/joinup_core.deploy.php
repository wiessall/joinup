<?php

/**
 * @file
 * Deploy functions for Joinup.
 *
 * This should only contain update functions that rely on the Drupal API and
 * need to run _after_ the configuration is imported.
 *
 * This is applicable in most cases. However in case the update code enables
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
 * Migrate Video content into new Media field.
 */
function joinup_core_deploy_0108701(): string {
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
    migrate_field($media_type, 'video', 'node', (int) $node->id(), 'field_video');
    $sandbox['count']++;
  }

  $sandbox['#finished'] = $sandbox['count'] === $sandbox['max'];
  return "Migrate {$sandbox['count']} out of {$sandbox['max']} video embed field.";
}

/**
 * Migrate Video Embed Field to Media.
 *
 * @param \Drupal\media\Entity\MediaType $mediaType
 *   Media type.
 * @param string $refFieldName
 *   Field name.
 * @param string $entityType
 *   Entity type.
 * @param int $id
 *   Entity id.
 * @param string $legacyField
 *   Migration field.
 *
 * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
 * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
 * @throws \Drupal\Core\Entity\EntityStorageException
 */
function migrate_field(MediaType $mediaType, string $refFieldName, string $entityType, int $id, string $legacyField): void {
  $entity_type_manager = \Drupal::entityTypeManager();
  $mediaStorage = $entity_type_manager->getStorage('media');
  $storage = $entity_type_manager->getStorage($entityType);
  $sourceField = $mediaType->getSource()->getSourceFieldDefinition($mediaType);
  $fieldValues = [];

  /** @var \Drupal\Core\Entity\ContentEntityBase $targetEntity */
  $targetEntity = $storage->load($id);
  $videoUrl = $targetEntity->get($legacyField)->value;
  $result = $mediaStorage->getQuery()
    ->condition('bundle', $mediaType->id())
    ->condition($sourceField->getName(), $videoUrl)
    ->execute();
  if (empty($result)) {
    $videoMedia = $mediaStorage->create([
      'bundle' => $mediaType->id(),
      $sourceField->getName() => [
        'value' => $videoUrl,
      ],
    ]);
    $videoMedia->setOwnerId($targetEntity->getOwner()->id());
    $videoMedia->save();
  }
  else {
    $videoMedia = $mediaStorage->load(reset($result));
  }
  $fieldValues[] = ['target_id' => $videoMedia->id()];
  $targetEntity->set($refFieldName, $fieldValues);
  $targetEntity->save();
}
