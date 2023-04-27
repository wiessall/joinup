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

use Drupal\media\MediaTypeInterface;
use Drupal\sparql_entity_storage\UriEncoder;

/**
 * Fix glossary link for solution entities.
 */
function joinup_core_deploy_109200(array &$sandbox = []): string {
  /** @var \Drupal\sparql_entity_storage\SparqlEntityStorageInterface $solutionStorage */
  $solutionStorage = \Drupal::entityTypeManager()
    ->getStorage('rdf_entity');

  /** @var \Drupal\menu_link_content\MenuLinkContentStorageInterface $menuLinkContentStorage */
  $menuLinkContentStorage = \Drupal::entityTypeManager()
    ->getStorage('menu_link_content');

  if (empty($sandbox['ids'])) {
    $solutionIds = $solutionStorage->getQuery()
      ->accessCheck(FALSE)
      ->condition('rid', 'solution')
      ->execute();

    $sandbox['ids'] = array_values($solutionIds);
    $sandbox['max'] = count($sandbox['ids']);
    $sandbox['current'] = 0;
  }

  $ids = array_splice($sandbox['ids'], 0, 100);

  /** @var \Drupal\solution\Entity\SolutionInterface $solution */
  foreach ($solutionStorage->loadMultiple($ids) as $solution) {
    $menuIds = $menuLinkContentStorage->getQuery()
      ->accessCheck(FALSE)
      ->condition('menu_name', 'ogmenu-', 'STARTS_WITH')
      ->condition('title', 'Glossary')
      ->condition('link.uri', 'route:entity.rdf_entity.glossary', 'STARTS_WITH')
      ->condition('link.uri', UriEncoder::encodeUrl($solution->id()), 'ENDS_WITH')
      ->execute();

    if (empty($menuIds)) {
      continue;
    }

    /** @var Drupal\menu_link_content\Entity\MenuLinkContent $menuLinkContent */
    $menuLinkContent = $menuLinkContentStorage->load(reset($menuIds));
    if (!$menuLinkContent) {
      continue;
    }

    $menuLinkContent->link->uri = str_replace(
      UriEncoder::encodeUrl($solution->id()),
      UriEncoder::encodeUrl($solution->getCollection()->id()),
      $menuLinkContent->link->uri
    );
    $menuLinkContent->save();
  }

  $sandbox['current'] += count($ids);
  $sandbox['#finished'] = (int) empty($sandbox['ids']);

  return "Updated {$sandbox['current']} of {$sandbox['max']} glossary menu links.";
}

/**
 * Adding blocked users before the spam activity (03/12/2022) to queue.
 */
function joinup_core_deploy_109201(): string {
  $from_date = new \DateTime("2022/12/03");
  $users_ids = \Drupal::entityQuery('user')
    ->accessCheck(FALSE)
    ->condition('status', '0')
    ->condition('uid', 1, '>')
    ->condition('created', $from_date->getTimestamp(), '<=')
    ->execute();

  $count = count($users_ids);
  $queue = \Drupal::queue('joinup_removed_users');
  $queue->createQueue();

  while (!empty($users_ids)) {
    $ids = array_splice($users_ids, 0, 10);
    $queue->createItem($ids);
  }

  return "Add $count users to queue.";
}

/**
 * Migrate Video content into new Media field.
 */
function joinup_core_deploy_109202(array &$sandbox): string {
  if (empty($sandbox['ids'])) {
    $sandbox['ids'] = \Drupal::entityQuery('node')
      ->accessCheck(FALSE)
      ->condition('type', 'video')->execute();
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
function _joinup_core_deploy_migrate_field(MediaTypeInterface $media_type, string $ref_field_name, string $entity_type, int $id, string $legacy_field): void {
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
    $result = $media_storage->getQuery()->accessCheck(FALSE)
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
function joinup_core_deploy_109203(array &$sandbox): string {
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
  $result = $media_storage->getQuery()->accessCheck(FALSE)
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
