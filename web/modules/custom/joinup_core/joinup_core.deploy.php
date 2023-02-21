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

use Drupal\Component\Utility\Html;
use Drupal\media\Entity\MediaType;
use Drupal\meta_entity\Entity\MetaEntity;

/**
 * Update the topic vocabulary terms.
 */
function joinup_core_deploy_109000(array &$sandbox): string {
  if (empty($sandbox['tasks'])) {
    $sandbox['tasks'] = [
      'createTopicTerm' => [
        [
          'Digital government',
          NULL,
        ],
        [
          'Energy and environment',
          NULL,
        ],
        [
          'Energy and environment',
          'http://joinup.eu/ontology/topic/category#energy-and-environment',
        ],
        [
          'Smart cities',
          'http://joinup.eu/ontology/topic/category#digital-government',
        ],
        [
          'Legal',
          'http://joinup.eu/ontology/topic/category#digital-government',
        ],
        [
          'Observatories',
          'http://joinup.eu/ontology/topic/category#digital-government',
        ],
        [
          'Digital ready policymaking',
          'http://joinup.eu/ontology/topic/category#digital-government',
        ],
        [
          'Tools',
          'http://joinup.eu/ontology/topic/category#digital-government',
        ],
        [
          'Semantics',
          'http://joinup.eu/ontology/topic/category#ict',
        ],
        [
          'Reusable Software Components',
          'http://joinup.eu/ontology/topic/category#ict',
        ],
        [
          'Innovation',
          'http://joinup.eu/ontology/topic/category#ict',
        ],
      ],
      'moveTopicChildrenToNewParent' => [
        [
          'source' => 'http://joinup.eu/ontology/topic/category#egovernment',
          'destination' => 'http://joinup.eu/ontology/topic/category#digital-government',
        ],
        [
          'source' => 'http://joinup.eu/ontology/topic/category#energy',
          'destination' => 'http://joinup.eu/ontology/topic/category#energy-and-environment',
        ],
        [
          'source' => 'http://joinup.eu/ontology/topic/category#environment',
          'destination' => 'http://joinup.eu/ontology/topic/category#energy-and-environment',
        ],
        [
          'source' => 'http://joinup.eu/ontology/topic/category#industry-and-production',
          'destination' => 'http://joinup.eu/ontology/topic/category#economics',
        ],
        [
          'source' => 'http://joinup.eu/ontology/topic/category#information',
          'destination' => 'http://joinup.eu/ontology/topic/category#ict',
        ],
        [
          'source' => 'http://joinup.eu/ontology/topic/category#law',
          'destination' => 'http://joinup.eu/ontology/topic/category#digital-government',
        ],
        [
          'source' => 'http://joinup.eu/ontology/topic/category#science-and-technology',
          'destination' => 'http://joinup.eu/ontology/topic/category#education',
        ],
        [
          'source' => 'http://joinup.eu/ontology/topic/category#social',
          'destination' => 'http://joinup.eu/ontology/topic/category#digital-government',
        ],
      ],
      'renameTopic' => [
        [
          'http://joinup.eu/ontology/topic#free-and-open-source-software-in-public-administrations',
          'Open Source Software',
        ],
        [
          'http://joinup.eu/ontology/topic#ict-standardisation-in-egovernment',
          'Standardisation',
        ],
        [
          'http://joinup.eu/ontology/topic#statistics',
          'Statistics and information',
        ],
        [
          'http://joinup.eu/ontology/topic#culture',
          'Culture and education',
        ],
      ],
      'changeTopicId' => [
        // All couples that have their topic IDs changed, also appear in the
        // updateDbReference below.
        [
          'http://joinup.eu/ontology/topic#free-and-open-source-software-in-public-administrations',
          'http://joinup.eu/ontology/topic#open-source-software',
        ],
        [
          'http://joinup.eu/ontology/topic#ict-standardisation-in-egovernment',
          'http://joinup.eu/ontology/topic#standardisation',
        ],
        [
          'http://joinup.eu/ontology/topic#statistics',
          'http://joinup.eu/ontology/topic#statistics-and-informatino',
        ],
        [
          'http://joinup.eu/ontology/topic#culture',
          'http://joinup.eu/ontology/topic#culture-and-education',
        ],
      ],
      'changeTopicParent' => [
        [
          'http://joinup.eu/ontology/topic#business-and-competition',
          'http://joinup.eu/ontology/topic/category#digital-government',
        ],
        [
          'http://joinup.eu/ontology/topic#open-source-software',
          'http://joinup.eu/ontology/topic/category#ict',
        ],
        [
          'http://joinup.eu/ontology/topic#content-and-knowledge-management',
          'http://joinup.eu/ontology/topic/category#ict',
        ],
        [
          'http://joinup.eu/ontology/topic#statistics',
          'http://joinup.eu/ontology/topic/category#ict',
        ],
        [
          'http://joinup.eu/ontology/topic#european-policies',
          'http://joinup.eu/ontology/topic/category#digital-government',
        ],
        [
          'http://joinup.eu/ontology/topic#eparticipation-and-evoting',
          'http://joinup.eu/ontology/topic/category#digital-government',
        ],
        [
          'http://joinup.eu/ontology/topic#standardisation',
          'http://joinup.eu/ontology/topic/category#ict',
        ],
        [
          'http://joinup.eu/ontology/topic#science-and-technology',
          'http://joinup.eu/ontology/topic/category#education',
        ],
        [
          'http://joinup.eu/ontology/topic#demography-and-population',
          'http://joinup.eu/ontology/topic/category#digital-government',
        ],
        [
          'http://joinup.eu/ontology/topic#einclusion',
          'http://joinup.eu/ontology/topic/category#digital-government',
        ],
        [
          'http://joinup.eu/ontology/topic#social',
          'http://joinup.eu/ontology/topic/category#digital-government',
        ],
        [
          'http://joinup.eu/ontology/topic#geospatial',
          'http://joinup.eu/ontology/topic/category#transport',
        ],
      ],
      'updateDbReference' => [
        [
          'source' => 'http://joinup.eu/ontology/topic#defence',
          'destination' => 'http://joinup.eu/ontology/topic#ict-security',
        ],
        [
          'source' => 'http://joinup.eu/ontology/topic#economic-analysis',
          'destination' => 'http://joinup.eu/ontology/topic#banking-and-financial-services',
        ],
        [
          'source' => 'http://joinup.eu/ontology/topic#eu-finance',
          'destination' => 'http://joinup.eu/ontology/topic#banking-and-financial-services',
        ],
        [
          'source' => 'http://joinup.eu/ontology/topic#free-and-open-source-software-in-public-administrations',
          'destination' => 'http://joinup.eu/ontology/topic#open-source-software',
        ],
        [
          'source' => 'http://joinup.eu/ontology/topic#free-and-open-source-software',
          'destination' => 'http://joinup.eu/ontology/topic#open-source-software',
        ],
        [
          'source' => 'http://joinup.eu/ontology/topic#ict-standardisation-in-egovernment',
          'destination' => 'http://joinup.eu/ontology/topic#standardisation',
        ],
        [
          'source' => 'http://joinup.eu/ontology/topic#ict-standardisation',
          'destination' => 'http://joinup.eu/ontology/topic#standardisation',
        ],
        [
          'source' => 'http://joinup.eu/ontology/topic#open-government',
          'destination' => 'http://joinup.eu/ontology/topic#egovernment',
        ],
        [
          'source' => 'http://joinup.eu/ontology/topic#energy',
          'destination' => 'http://joinup.eu/ontology/topic#energy-and-environment',
        ],
        [
          'source' => 'http://joinup.eu/ontology/topic#environment',
          'destination' => 'http://joinup.eu/ontology/topic#energy-and-environment',
        ],
        [
          'source' => 'http://joinup.eu/ontology/topic#authorisation-and-authentication',
          'destination' => 'http://joinup.eu/ontology/topic#eidentity-and-esignature',
        ],
        [
          'source' => 'http://joinup.eu/ontology/topic#information',
          'destination' => 'http://joinup.eu/ontology/topic#content-and-knowledge-management',
        ],
        [
          'source' => 'http://joinup.eu/ontology/topic#security',
          'destination' => 'http://joinup.eu/ontology/topic#ict-security',
        ],
        [
          'source' => 'http://joinup.eu/ontology/topic#politics',
          'destination' => 'http://joinup.eu/ontology/topic#european-policies',
        ],
        [
          'source' => 'http://joinup.eu/ontology/topic#statistics',
          'destination' => 'http://joinup.eu/ontology/topic#statistics-and-informatino',
        ],
        [
          'source' => 'http://joinup.eu/ontology/topic#education',
          'destination' => 'http://joinup.eu/ontology/topic#culture-and-education',
        ],
        [
          'source' => 'http://joinup.eu/ontology/topic#law',
          'destination' => 'http://joinup.eu/ontology/topic#legal',
        ],
        [
          'source' => 'http://joinup.eu/ontology/topic#ejustice',
          'destination' => 'http://joinup.eu/ontology/topic#legal',
        ],
      ],
      'createRedirect' => [
        [
          'source' => 'http://joinup.eu/ontology/topic/category#egovernment',
          'destination' => 'http://joinup.eu/ontology/topic/category#digital-government',
        ],
        [
          'source' => 'http://joinup.eu/ontology/topic/category#energy',
          'destination' => 'http://joinup.eu/ontology/topic/category#energy-and-environment',
        ],
        [
          'source' => 'http://joinup.eu/ontology/topic/category#environment',
          'destination' => 'http://joinup.eu/ontology/topic/category#energy-and-environment',
        ],
        [
          'source' => 'http://joinup.eu/ontology/topic/category#industry-and-production',
          'destination' => 'http://joinup.eu/ontology/topic/category#economics',
        ],
        [
          'source' => 'http://joinup.eu/ontology/topic/category#information',
          'destination' => 'http://joinup.eu/ontology/topic/category#ict',
        ],
        [
          'source' => 'http://joinup.eu/ontology/topic/category#law',
          'destination' => 'http://joinup.eu/ontology/topic/category#digital-government',
        ],
        [
          'source' => 'http://joinup.eu/ontology/topic/category#science-and-technology',
          'destination' => 'http://joinup.eu/ontology/topic/category#education',
        ],
        [
          'source' => 'http://joinup.eu/ontology/topic/category#social',
          'destination' => 'http://joinup.eu/ontology/topic/category#digital-government',
        ],
        [
          'source' => 'http://joinup.eu/ontology/topic#defence',
          'destination' => 'http://joinup.eu/ontology/topic#ict-security',
        ],
        [
          'source' => 'http://joinup.eu/ontology/topic#economic-analysis',
          'destination' => 'http://joinup.eu/ontology/topic#banking-and-financial-services',
        ],
        [
          'source' => 'http://joinup.eu/ontology/topic#eu-finance',
          'destination' => 'http://joinup.eu/ontology/topic#banking-and-financial-services',
        ],
        [
          'source' => 'http://joinup.eu/ontology/topic#open-government',
          'destination' => 'http://joinup.eu/ontology/topic#egovernment',
        ],
        [
          'source' => 'http://joinup.eu/ontology/topic#energy',
          'destination' => 'http://joinup.eu/ontology/topic#energy-and-environment',
        ],
        [
          'source' => 'http://joinup.eu/ontology/topic#environment',
          'destination' => 'http://joinup.eu/ontology/topic#energy-and-environment',
        ],
        [
          'source' => 'http://joinup.eu/ontology/topic#authorisation-and-authentication',
          'destination' => 'http://joinup.eu/ontology/topic#eidentity-and-esignature',
        ],
        [
          'source' => 'http://joinup.eu/ontology/topic#information',
          'destination' => 'http://joinup.eu/ontology/topic#content-and-knowledge-management',
        ],
        [
          'source' => 'http://joinup.eu/ontology/topic#security',
          'destination' => 'http://joinup.eu/ontology/topic#ict-security',
        ],
        [
          'source' => 'http://joinup.eu/ontology/topic#politics',
          'destination' => 'http://joinup.eu/ontology/topic#european-policies',
        ],
        [
          'source' => 'http://joinup.eu/ontology/topic/category#geospatial',
          'destination' => 'http://joinup.eu/ontology/topic/category#transport',
        ],
        [
          'source' => 'http://joinup.eu/ontology/topic#law',
          'destination' => 'http://joinup.eu/ontology/topic#legal',
        ],
        [
          'source' => 'http://joinup.eu/ontology/topic#ejustice',
          'destination' => 'http://joinup.eu/ontology/topic#legal',
        ],
        [
          'source' => 'http://joinup.eu/ontology/topic#ict-standardisation',
          'destination' => 'http://joinup.eu/ontology/topic#standardisation',
        ],
      ],
      // All terms should be empty in references/children after the migration.
      'deleteTopicTerm' => [
        'http://joinup.eu/ontology/topic#authorisation-and-authentication',
        'http://joinup.eu/ontology/topic#culture',
        'http://joinup.eu/ontology/topic#defence',
        'http://joinup.eu/ontology/topic#economic-analysis',
        'http://joinup.eu/ontology/topic#education',
        'http://joinup.eu/ontology/topic#ejustice',
        'http://joinup.eu/ontology/topic#energy',
        'http://joinup.eu/ontology/topic#environment',
        'http://joinup.eu/ontology/topic#eu-finance',
        'http://joinup.eu/ontology/topic#free-and-open-source-software',
        'http://joinup.eu/ontology/topic#free-and-open-source-software-in-public-administrations',
        'http://joinup.eu/ontology/topic#ict-standardisation',
        'http://joinup.eu/ontology/topic#information',
        'http://joinup.eu/ontology/topic#law',
        'http://joinup.eu/ontology/topic#open-government',
        'http://joinup.eu/ontology/topic#politics',
        'http://joinup.eu/ontology/topic#security',
        'http://joinup.eu/ontology/topic#statistics',
        'http://joinup.eu/ontology/topic/category#business-and-competition',
        'http://joinup.eu/ontology/topic/category#defence',
        'http://joinup.eu/ontology/topic/category#egovernment',
        'http://joinup.eu/ontology/topic/category#energy',
        'http://joinup.eu/ontology/topic/category#environment',
        'http://joinup.eu/ontology/topic/category#geospatial',
        'http://joinup.eu/ontology/topic/category#industry-and-production',
        'http://joinup.eu/ontology/topic/category#information',
        'http://joinup.eu/ontology/topic/category#law',
        'http://joinup.eu/ontology/topic/category#politics',
        'http://joinup.eu/ontology/topic/category#science-and-technology',
        'http://joinup.eu/ontology/topic/category#social',
      ],
    ];

    $sandbox['current'] = 0;
    $sandbox['max'] = 0;
    foreach ($sandbox['tasks'] as $data) {
      $sandbox['max'] += count($data);
    }
  }

  $vocab_manager = \Drupal::getContainer()->get('topic.vocabulary_manager');
  $operation = array_key_first($sandbox['tasks']);
  $slow_tasks = ['createRedirect', 'deleteTopicTerm'];
  $operations_per_batch = in_array($operation, $slow_tasks) ? 1 : 10;
  $tasks = array_splice($sandbox['tasks'][$operation], 0, $operations_per_batch);
  if (empty($sandbox['tasks'][$operation])) {
    unset($sandbox['tasks'][$operation]);
  }

  foreach ($tasks as $task) {
    switch ($operation) {
      case 'createTopicTerm':
      case 'renameTopic':
      case 'changeTopicId':
      case 'changeTopicParent':
        $vocab_manager->{$operation}($task[0], $task[1]);
        break;

      case 'moveTopicChildrenToNewParent':
      case 'updateDbReference':
      case 'createRedirect':
        $vocab_manager->{$operation}($task['source'], $task['destination']);
        break;

      case 'deleteTopicTerm':
        $vocab_manager->{$operation}($task);
        break;

      default:
        break;
    }
    $sandbox['current']++;
  }

  $sandbox['#finished'] = empty($sandbox['tasks']) ? 1 : ($sandbox['current'] / $sandbox['max']);
  return "Topic refactoring: $operation, " . count($tasks) . " tasks have been completed. Total progress: {$sandbox['current']} / {$sandbox['max']}";
}

/**
 * Synchronize Topic meta entity with Topic terms.
 */
function joinup_core_deploy_109001(): void {
  $storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
  /** @var \Drupal\taxonomy\TermInterface $term */
  foreach ($storage->loadByProperties(['vid' => 'topic']) as $term) {
    // Re-save existing meta entities as well to get the correct path alias as
    // that might have been wrongly computed in the previous function.
    MetaEntity::loadOrCreate('topic', $term)->save();
  }
}

/**
 * Migrate Video content into new Media field.
 */
function joinup_core_deploy_109002(array &$sandbox): string {
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
  $source_field = $media_type->getSource()
    ->getSourceFieldDefinition($media_type);
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

/**
 * Migrate video embed markup into media.
 */
function joinup_core_deploy_109003(array &$sandbox): string {
  if (empty($sandbox['tasks'])) {
    $sandbox['tasks']['node'] = \Drupal::entityQuery('node')
      ->condition('body', 'preview_thumbnail', 'CONTAINS')
      ->execute();
    $sandbox['tasks']['paragraph'] = \Drupal::entityQuery('paragraph')
      ->condition('field_body', 'preview_thumbnail', 'CONTAINS')
      ->execute();
    $rdf_query = \Drupal::entityQuery('rdf_entity');
    $group = $rdf_query->orConditionGroup()
      ->condition('field_ad_description', 'preview_thumbnail', 'CONTAINS')
      ->condition('field_isr_release_notes', 'preview_thumbnail', 'CONTAINS')
      ->condition('field_ar_description', 'preview_thumbnail', 'CONTAINS')
      ->condition('field_licence_description', 'preview_thumbnail', 'CONTAINS')
      ->condition('field_is_description', 'preview_thumbnail', 'CONTAINS');
    $sandbox['tasks']['rdf_entity'] = $rdf_query->condition($group)->execute();

    $sandbox['count'] = 0;
    $sandbox['max'] = count($sandbox['tasks']['node']) + count($sandbox['tasks']['paragraph']) + count($sandbox['tasks']['rdf_entity']);
  }

  $entity_type_manager = \Drupal::entityTypeManager();
  $type = array_key_first($sandbox['tasks']);
  $tasks = array_splice($sandbox['tasks'][$type], 0, 25);
  if (empty($sandbox['tasks'][$type])) {
    unset($sandbox['tasks'][$type]);
  }

  foreach ($tasks as $entity_id) {
    $entity = $entity_type_manager->getStorage($type)->load($entity_id);
    if ($type === 'rdf_entity') {
      $query = "SELECT ?description WHERE { <{$entity_id}> <http://purl.org/dc/terms/description> ?description . }";
      $sparl = \Drupal::service('sparql.endpoint');
      $results = $sparl->query($query);
      /** @var \EasyRdf\Sparql\Result $results */
      foreach ($results as $result) {
        $old_description = Html::escape((string) $result->description);
        preg_match_all('/{&quot;preview_thumbnail&quot;:[^\]]+\]}/', $old_description, $videos_embed);
        foreach ($videos_embed as $video_embed) {
          preg_match('/&quot;video_url&quot;:&quot;(.*?)&quot;/', $video_embed[0], $video_url);
          $media_uuid = _joinup_core_deploy_replace_media($video_url[1], $entity->getOwner()->id());
          $new_video = '<drupal-media data-entity-type="media" data-entity-uuid="' . $media_uuid . '">&nbsp;</drupal-media>';
          $new_description = preg_replace('/{&quot;preview_thumbnail&quot;:[^\]]+\]}/', $new_video, $old_description);
          $new_description = Html::decodeEntities($new_description);
          $graph = "<http://joinup.eu/{$entity->bundle()}/published>";
          $update_query = <<<QUERY
          WITH $graph
          DELETE { <$entity_id> <http://purl.org/dc/terms/description> ?description }
          INSERT { <$entity_id> <http://purl.org/dc/terms/description> $new_description }
          WHERE { <$entity_id> <http://purl.org/dc/terms/description> ?description }
          QUERY;
          $sparl->query($update_query);
        }
      }
    }
    if ($type === 'node' || $type === 'paragraph') {
      $table = $type === 'node' ? 'node__body' : 'paragraph__field_body';
      $field = $type === 'node' ? 'body_value' : 'field_body_value';
      $database = \Drupal::database();
      $results = $database->query("SELECT revision_id, " . $field . " FROM " . $table . " WHERE entity_id = " . $entity_id)->fetchAll();
      foreach ($results as $result) {
        $old_description = Html::escape($result->{$field});
        preg_match_all('/{&quot;preview_thumbnail&quot;:[^\]]+\]}/', $old_description, $videos_embed);
        foreach ($videos_embed as $video_embed) {
          preg_match('/&quot;video_url&quot;:&quot;(.*?)&quot;/', $video_embed[0], $video_url);
          $media_uuid = _joinup_core_deploy_replace_media($video_url[1], $entity->getOwner()->id());
          $new_video = '<drupal-media data-entity-type="media" data-entity-uuid="' . $media_uuid . '">&nbsp;</drupal-media>';
          $new_description = preg_replace('/{&quot;preview_thumbnail&quot;:[^\]]+\]}/', $new_video, $old_description);
          $database->update($table)
            ->fields([
              $field => Html::decodeEntities($new_description),
            ])
            ->condition('revision_id', $result->revision_id)
            ->execute();
        }
      }
    }
    $sandbox['count']++;
  }

  $sandbox['#finished'] = $sandbox['count'] === $sandbox['max'];
  return "Migrate {$sandbox['count']} out of {$sandbox['max']} video embed markup into media.";
}

/**
 * Migrate Video Embed WYSIWYG to Media.
 *
 * @param string $url
 *   Video URL.
 * @param string $owner_id
 *   Owner ID.
 *
 * @return string
 *   Return the media uuid.
 *
 * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
 * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
 * @throws \Drupal\Core\Entity\EntityStorageException
 */
function _joinup_core_deploy_replace_media(string $url, string $owner_id): string {
  $media_storage = \Drupal::entityTypeManager()->getStorage('media');
  $result = $media_storage->getQuery()
    ->condition('bundle', 'video')
    ->condition('field_media_video_embed_field', $url)
    ->execute();
  if (empty($result)) {
    $video_media = $media_storage->create([
      'bundle' => 'video',
      'field_media_video_embed_field' => [
        'value' => $url,
      ],
    ]);
    $video_media->setOwnerId($owner_id);
    $video_media->save();
  }
  else {
    $video_media = $media_storage->load(reset($result));
  }
  return $video_media->uuid();
}
