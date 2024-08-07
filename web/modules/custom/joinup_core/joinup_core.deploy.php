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

declare(strict_types=1);

use Drupal\Core\Utility\UpdateException;
use Drupal\rdf_sync\RdfSyncMapper;
use Drupal\topic\Entity\TopicMetaEntityInterface;

/**
 * Fixes broken topic meta entities and deletes the duplications.
 */
function joinup_core_deploy_111101(): string {
  $metaEntityStorage = \Drupal::entityTypeManager()->getStorage('meta_entity');
  $topicMetas = $metaEntityStorage->loadByProperties(['type' => 'topic']);
  // Currently, there are 8 published topic meta entities, all of them are
  // displayed on the home page, populated with user-provided content in their
  // layout paragraph field.
  // So, we fix these 'preexisting' metas and delete the ones created
  // accidentally, during the SPARQL -> SQL storage switch.
  $oldButBrokenMetas = array_filter(
    $topicMetas,
    function (TopicMetaEntityInterface $meta): bool {
      return !is_numeric($meta->target?->get(0)?->getValue()['target_id'] ?? NULL);
    }
  );

  // Before we delete the unnecessary metas, make sure we can find the right
  // target for all the old ones.
  $rdfSyncMapper = \Drupal::service('rdf_sync.mapper');
  assert($rdfSyncMapper instanceof RdfSyncMapper);
  $topicMetaTargetMapping = array_reduce(
    $oldButBrokenMetas,
    function (array $carry, TopicMetaEntityInterface $topicMeta) use ($rdfSyncMapper): array {
      $targetIdUriOriginal = $topicMeta->get('target')->get(0)?->getValue()['target_id'] ?? NULL;
      $targetIdUri = match($targetIdUriOriginal) {
        'http://joinup.eu/ontology/topic#statistics-and-informatino' => 'http://joinup.eu/ontology/topic#statistics-and-information',
        default => $targetIdUriOriginal,
      };
      $carry[$topicMeta->id()] = $rdfSyncMapper->getEntityByUri($targetIdUri)->id();
      return $carry;
    },
    [],
  );
  if (count($oldButBrokenMetas) !== count(array_filter($topicMetaTargetMapping))) {
    throw new UpdateException('It is not possible to restore the relationship of all broken topic meta entities, aborting deploy hook');
  }

  // Delete new, unnecessary topic meta entities.
  $deleted = 0;
  foreach (array_diff_key($topicMetas, $oldButBrokenMetas) as $potentialDuplicate) {
    assert($potentialDuplicate instanceof TopicMetaEntityInterface);
    if (!in_array($potentialDuplicate->getTargetEntity()->id(), $topicMetaTargetMapping, TRUE)) {
      // We don't have a preexisting, broken (but fixable) meta entity which can
      // replace this one, so skip deletion.
      continue;
    }
    $potentialDuplicate->delete();
    $deleted++;
  }

  // Fix the broken topic term metas.
  foreach ($oldButBrokenMetas as $brokenMeta) {
    assert($brokenMeta instanceof TopicMetaEntityInterface);
    $brokenMeta->get('target')->get(0)
      ->set('target_id', $topicMetaTargetMapping[$brokenMeta->id()]);
    $brokenMeta->save();
  }

  return sprintf(
    "Fixed %s broken topic meta entities and deleted %s duplication.",
    count($oldButBrokenMetas),
    $deleted,
  );
}
