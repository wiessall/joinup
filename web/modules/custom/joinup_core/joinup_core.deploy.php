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

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\joinup_group\Entity\GroupContentInterface;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Migrate Video content into new Media field.
 */
function joinup_core_deploy_109400(array &$sandbox): string {
  $entity_type_manager = \Drupal::entityTypeManager();
  $node_storage = $entity_type_manager->getStorage('node');
  $media_storage = $entity_type_manager->getStorage('media');

  if (empty($sandbox['ids'])) {
    $sandbox['ids'] = $node_storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', 'video')
      ->execute();
    $sandbox['count'] = 0;
    $sandbox['max'] = count($sandbox['ids']);
  }

  $nids = array_splice($sandbox['ids'], 0, 50);
  /** @var \Drupal\joinup_video\Entity\VideoInterface $video */
  foreach ($node_storage->loadMultiple($nids) as $video) {
    if ($url = $video->get('field_video')->value) {
      $media = $media_storage->getQuery()->accessCheck(FALSE)
        ->condition('bundle', 'video')
        ->condition('video', $url)
        ->execute();
      if (!$media) {
        $media = $media_storage->create(['bundle' => 'video', 'video' => $url])
          ->setOwnerId($video->getOwnerId())
          ->set('og_audience', $video->get('og_audience')->target_id);
      }
      else {
        $media = reset($media);
      }
      $video->set('video', $media)->save();
    }
    $sandbox['count']++;
  }
  $sandbox['#finished'] = $sandbox['count'] === $sandbox['max'];

  return "Migrate {$sandbox['count']} out of {$sandbox['max']} video embed fields.";
}

/**
 * Migrate video embed markup into media.
 */
function joinup_core_deploy_109401(array &$sandbox): string {
  $sparql = \Drupal::getContainer()->get('sparql.endpoint');
  $database = \Drupal::database();
  $entity_type_manager = \Drupal::entityTypeManager();
  $entity_type_ids = ['node', 'paragraph', 'rdf_entity'];
  $storage = array_map(
    fn(string $entity_type_id): EntityStorageInterface => $entity_type_manager->getStorage($entity_type_id),
    array_combine($entity_type_ids, $entity_type_ids),
  );
  $predicates = [
    'http://purl.org/dc/terms/description',
    'http://www.w3.org/ns/adms#versionNotes',
  ];
  $fields = ['node' => 'body', 'paragraph' => 'field_body'];

  if (empty($sandbox['tasks'])) {
    $sandbox['tasks']['node'] = \Drupal::entityQuery('node')->accessCheck(FALSE)
      ->condition('body', 'preview_thumbnail', 'CONTAINS')
      ->execute();
    $sandbox['tasks']['paragraph'] = \Drupal::entityQuery('paragraph')
      ->condition('field_body', 'preview_thumbnail', 'CONTAINS')
      ->execute();
    $rdf_query = \Drupal::entityQuery('rdf_entity')->accessCheck(FALSE);
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

  $type = array_key_first($sandbox['tasks']);
  $tasks = array_splice($sandbox['tasks'][$type], 0, 25);
  if (empty($sandbox['tasks'][$type])) {
    unset($sandbox['tasks'][$type]);
  }

  foreach ($tasks as $entity_id) {
    /** @var \Drupal\Core\Entity\ContentEntityInterface&\Drupal\user\EntityOwnerInterface $entity */
    $entity = $storage[$type]->load($entity_id);
    if ($type === 'rdf_entity') {
      foreach ($predicates as $predicate) {
        $results = $sparql->query("SELECT ?description WHERE { <$entity_id> <$predicate> ?description . }");
        /** @var \EasyRdf\Sparql\Result $results */
        foreach ($results as $result) {
          $description = (string) $result->description;
          $processed_text = _joinup_core_deploy_replace_embed_video($entity, $description);
          if ($processed_text !== $description) {
            $update_query = <<<QUERY
              WITH <http://joinup.eu/{$entity->bundle()}/published>
              DELETE { <$entity_id> <$predicate> ?description }
              INSERT { <$entity_id> <$predicate> """$processed_text"""@en }
              WHERE { <$entity_id> <$predicate> ?description }
              QUERY;
            $sparql->update($update_query);
          }
        }
      }
    }
    elseif ($type === 'node' || $type === 'paragraph') {
      $field = "$fields[$type]_value";
      foreach (["{$type}_revision__$fields[$type]", "${type}__$fields[$type]"] as $table) {
        $results = $database->query("SELECT revision_id, $field FROM $table WHERE entity_id = $entity_id")->fetchAll();
        foreach ($results as $result) {
          $processed_text = _joinup_core_deploy_replace_embed_video($entity, $result->{$field});
          if ($processed_text !== $result->{$field}) {
            $database->update($table)->fields([$field => $processed_text])
              ->condition('revision_id', $result->revision_id)
              ->execute();
          }
        }
      }
    }
    $sandbox['count']++;
  }

  $sandbox['#finished'] = $sandbox['count'] === $sandbox['max'];
  return "Migrate {$sandbox['count']} out of {$sandbox['max']} video embed markup into media.";
}

/**
 * Replaces embed videos with embed media.
 *
 * @param \Drupal\user\EntityOwnerInterface&\Drupal\Core\Entity\ContentEntityInterface $entity
 *   The host entity.
 * @param string $text
 *   Text to be processed.
 *
 * @return string
 *   Text after has been processed.
 */
function _joinup_core_deploy_replace_embed_video(ContentEntityInterface&EntityOwnerInterface $entity, string $text): string {
  $group_id = NULL;
  $search = $replace = [];

  // Get the media group ID.
  if ($entity instanceof GroupContentInterface && $entity->hasGroup()) {
    $group_id = $entity->getGroupId();
  }
  elseif ($entity instanceof ParagraphInterface) {
    $parent = $entity->getParentEntity();
    if ($parent instanceof GroupContentInterface && $parent->hasGroup()) {
      $group_id = $parent->getGroupId();
    }
  }

  if (preg_match_all('/(<p>)?(?<json>{(?=.*preview_thumbnail\b)(?=.*settings\b)(?=.*video_url\b)(?=.*settings_summary)(.*)})(<\/p>)?/', $text, $videos_embed)) {
    $media_storage = \Drupal::entityTypeManager()->getStorage('media');
    foreach ($videos_embed['json'] as $delta => $json) {
      // Ensure the JSON string is valid.
      if (($embed_data = json_decode($json, TRUE)) !== NULL) {
        $search[] = $videos_embed[0][$delta];
        $media_ids = $media_storage->getQuery()->accessCheck(FALSE)
          ->condition('bundle', 'video')
          ->condition('video', $embed_data['video_url'])
          ->execute();
        if ($media_ids) {
          $media = $media_storage->load(reset($media_ids));
        }
        else {
          $media = $media_storage->create([
            'bundle' => 'video',
            'video' => $embed_data['video_url'],
          ])->setOwnerId((int) $entity->getOwnerId());

          if ($group_id) {
            $media->set('og_audience', $group_id);
          }

          $media->save();
        }
        $replace[] = '<drupal-media data-entity-type="media" data-entity-uuid="' . $media->uuid() . '"/>';
      }
    }
  }

  return str_replace($search, $replace, $text);
}
