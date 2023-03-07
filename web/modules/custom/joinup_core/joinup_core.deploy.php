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
 * Migrate Video content into new Media field.
 */
function joinup_core_deploy_109100(array &$sandbox): string {
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
function joinup_core_deploy_109101(array &$sandbox): string {
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
      $predicates = [
        'http://purl.org/dc/terms/description',
        'http://www.w3.org/ns/adms#versionNotes',
      ];
      foreach ($predicates as $predicate) {
        $query = "SELECT ?description WHERE { <{$entity_id}> <{$predicate}> ?description . }";
        $sparl = \Drupal::getContainer()->get('sparql.endpoint');
        $results = $sparl->query($query);
        /** @var \EasyRdf\Sparql\Result $results */
        foreach ($results as $result) {
          if (!preg_match_all('/(<p>)?(?<json>{(?=.*preview_thumbnail\b)(?=.*settings\b)(?=.*video_url\b)(?=.*settings_summary)(.*)})(<\/p>)?/', (string) $result->description, $videos_embed)) {
            continue;
          }
          foreach ($videos_embed['json'] as $delta => $json) {
            // Ensure the JSON string is valid.
            $embed_data = json_decode($json, TRUE);
            $replace = _joinup_core_deploy_get_media_markup($embed_data['video_url'], $entity->getOwner() ? $entity->getOwner()->id() : '1');
            $description = str_replace($videos_embed[0][$delta], $replace, (string) $result->description);
            $graph = "<http://joinup.eu/{$entity->bundle()}/published>";
            $update_query = <<<QUERY
          WITH $graph
          DELETE { <$entity_id> <{$predicate}> ?description }
          INSERT { <$entity_id> <{$predicate}> """{$description}"""@en }
          WHERE { <$entity_id> <{$predicate}> ?description }
          QUERY;
            $sparl->update($update_query);
          }
        }
      }
    }
    if ($type === 'node' || $type === 'paragraph') {
      $table = $type === 'node' ? 'node__body' : 'paragraph__field_body';
      $field = $type === 'node' ? 'body_value' : 'field_body_value';
      $database = \Drupal::database();
      $results = $database->query("SELECT revision_id, " . $field . " FROM " . $table . " WHERE entity_id = " . $entity_id)->fetchAll();
      foreach ($results as $result) {
        if (!preg_match_all('/(<p>)?(?<json>{(?=.*preview_thumbnail\b)(?=.*settings\b)(?=.*video_url\b)(?=.*settings_summary)(.*)})(<\/p>)?/', $result->{$field}, $videos_embed)) {
          continue;
        }
        foreach ($videos_embed['json'] as $delta => $json) {
          // Ensure the JSON string is valid.
          $embed_data = json_decode($json, TRUE);
          $replace = _joinup_core_deploy_get_media_markup($embed_data['video_url'], $entity->getOwner() ? $entity->getOwner()->id() : '1');
          $description = str_replace($videos_embed[0][$delta], $replace, $result->{$field});
          $database->update($table)
            ->fields([
              $field => $description,
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
function _joinup_core_deploy_get_media_markup(string $url, string $owner_id): string {
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

  return '<drupal-media data-entity-type="media" data-entity-uuid="' . $video_media->uuid() . '"/>';
}
