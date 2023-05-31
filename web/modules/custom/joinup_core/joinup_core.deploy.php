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
use Drupal\Core\Template\Attribute;
use Drupal\collection\Entity\CollectionInterface;
use Drupal\media\MediaInterface;
use EasyRdf\Graph;

/**
 * Migrate Video content into new Media field.
 */
function joinup_core_deploy_109400(array &$sandbox): string {
  $entity_type_manager = \Drupal::entityTypeManager();
  $node_storage = $entity_type_manager->getStorage('node');

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
      $media = _joinup_core_get_media('video', $url, (int) $video->getOwnerId(), $video->get('og_audience')->target_id);
      $video->set('video', $media)->save();
    }
    $sandbox['count']++;
  }
  $sandbox['#finished'] = $sandbox['count'] === $sandbox['max'];

  return "Migrate {$sandbox['count']} out of {$sandbox['max']} video embed fields.";
}

/**
 * Migrate videos and images embed markup into media.
 */
function joinup_core_deploy_109401(array &$sandbox): string {
  $sparql = \Drupal::getContainer()->get('sparql.endpoint');
  $sparql_field_handler = \Drupal::getContainer()->get('sparql.field_handler');
  $sparql_graph_handler = \Drupal::getContainer()->get('sparql.graph_handler');
  $db = \Drupal::database();
  $entity_type_manager = \Drupal::entityTypeManager();
  $rdf_storage = $entity_type_manager->getStorage('rdf_entity');
  $fields = ['node' => 'body', 'paragraph' => 'field_body'];

  if (empty($sandbox['tasks'])) {
    $sql = <<<QUERY
      SELECT b.revision_id, g.og_audience_target_id, IF(b.revision_id = n.vid, 1, 0) AS current_revision, n.uid
      FROM {node_revision__body} b
      INNER JOIN {node__og_audience} g ON b.entity_id = g.entity_id
      INNER JOIN {node_field_data} n ON b.entity_id = n.nid
      WHERE b.deleted = 0
      AND (b.body_value LIKE '%preview_thumbnail%' OR b.body_value LIKE '%<img%')
      QUERY;
    $sandbox['tasks']['node'] = array_map(function (array $item): array {
      return [(int) $item[0], $item[1], (bool) $item[2], (int) $item[3]];
    }, $db->query($sql)->fetchAll(\PDO::FETCH_NUM));

    $sparql_query = <<<QUERY
      SELECT ?graph ?entityId
      FROM NAMED <http://joinup.eu/collection/published>
      FROM NAMED <http://joinup.eu/collection/draft>
      FROM NAMED <http://joinup.eu/solution/published>
      FROM NAMED <http://joinup.eu/solution/draft>
      FROM NAMED <http://joinup.eu/asset_release/published>
      FROM NAMED <http://joinup.eu/asset_release/draft>
      FROM NAMED <http://joinup.eu/asset_distribution/published>
      FROM NAMED <http://joinup.eu/asset_distribution/draft>
      WHERE {
        GRAPH ?graph {
          ?entityId ?predicate ?value .
          FILTER(?predicate IN (<http://www.w3.org/ns/adms#versionNotes>, <http://purl.org/dc/terms/description>)) .
          FILTER(REGEX(STR(?value), 'preview_thumbnail|data-entity-type="file"', "i")) .
        }
      }
      QUERY;
    foreach ($sparql->query($sparql_query) as $row) {
      $graph_id = str_ends_with((string) $row->graph, '/draft') ? 'draft' : 'default';
      $sandbox['tasks']['rdf_entity'][] = [$graph_id, (string) $row->entityId];
    }

    $sandbox['fids'] = $db->query("SELECT uuid, fid FROM {file_managed}")->fetchAllKeyed();
    $sandbox['count'] = 0;
    $sandbox['max'] = count($sandbox['tasks']['node']) + count($sandbox['tasks']['rdf_entity']);
  }

  $type = array_key_first($sandbox['tasks']);
  $size = $type === 'rdf_entity' ? 25 : 50;
  $tasks = array_splice($sandbox['tasks'][$type], 0, $size);
  if (empty($sandbox['tasks'][$type])) {
    unset($sandbox['tasks'][$type]);
  }

  foreach ($tasks as $task) {
    if ($type === 'rdf_entity') {
      [$graph_id, $entity_id] = $task;
      /** @var \Drupal\joinup_group\Entity\GroupContentInterface|\Drupal\collection\Entity\CollectionInterface $entity */
      if ($entity = $rdf_storage->load($entity_id, [$graph_id])) {
        $field_name = $entity->getDescriptionField();
        $text = $entity->get($field_name)->value;
        if (!empty($text)) {
          $group_id = $entity instanceof CollectionInterface ? $entity_id : $entity->getGroupId();
          $processed_text = _joinup_core_deploy_process_text($text, $group_id, (int) $entity->getOwnerId(), $sandbox);
          if ($processed_text !== $text) {
            $bundle = $entity->bundle();
            $predicate = $sparql_field_handler->getFieldPredicates($type, $field_name, NULL, $bundle)[$bundle];
            $graph_uri = $sparql_graph_handler->getBundleGraphUri($type, $bundle, $graph_id);
            $graph = new Graph($graph_uri);
            $graph->add($entity_id, $predicate, $processed_text);
            $triples = $graph->serialise('ntriples');
            $query = <<<QUERY
              WITH <$graph_uri>
              DELETE { <$entity_id> <$predicate> ?value }
              INSERT { $triples }
              WHERE { <$entity_id> <$predicate> ?value }
              QUERY;
            $sparql->update($query);
          }
        }
      }
    }
    elseif ($type === 'node' || $type === 'paragraph') {
      [$revision_id, $group_id, $is_current_revision, $uid] = $task;
      $field = "$fields[$type]_value";
      $revision_table = "{$type}_revision__$fields[$type]";
      $table = "{$type}__$fields[$type]";
      $text = $db->query("SELECT $field FROM {{$revision_table}} WHERE revision_id = $revision_id")->fetchField();
      $processed_text = _joinup_core_deploy_process_text($text, $group_id, $uid, $sandbox);
      if ($processed_text !== $text) {
        $db->update($revision_table)->fields([$field => $processed_text])
          ->condition('revision_id', $revision_id)
          ->execute();
        if ($is_current_revision) {
          $db->update($table)->fields([$field => $processed_text])
            ->condition('revision_id', $revision_id)
            ->execute();
        }
      }
    }
    $sandbox['count']++;
  }

  $sandbox['#finished'] = $sandbox['count'] === $sandbox['max'];
  return "Processed {$sandbox['count']} out of {$sandbox['max']} text blobs.";
}

/**
 * Replaces embed videos/images with embed media in a given text blob.
 *
 * @param string $text
 *   The text to be processed.
 * @param string $group_id
 *   The group ID.
 * @param int $uid
 *   The entity owner.
 * @param array $sandbox
 *   The deployment sandbox.
 *
 * @return string
 *   Text after has been processed.
 */
function _joinup_core_deploy_process_text(string $text, string $group_id, int $uid, array &$sandbox): string {
  $search = $replace = [];

  // Convert embed videos.
  if (str_contains($text, 'preview_thumbnail') && preg_match_all('/(<p>)?(?<json>{(?=.*preview_thumbnail\b)(?=.*settings\b)(?=.*video_url\b)(?=.*settings_summary)(.*)})(<\/p>)?/', $text, $videos_embed)) {
    foreach ($videos_embed['json'] as $delta => $json) {
      // Ensure the JSON string is valid.
      if (($embed_data = json_decode($json, TRUE)) !== NULL) {
        $search[] = $videos_embed[0][$delta];
        $attributes = new Attribute([
          'data-entity-type' => 'media',
          'data-entity-uuid' => _joinup_core_get_media('video', $embed_data['video_url'], $uid, $group_id)->uuid(),
        ]);
        $replace[] = "<drupal-media $attributes />";
      }
    }
  }
  $text = str_replace($search, $replace, $text);

  // Perform a bird-eye check to avoid useless heavy computation of DOM.
  if (!str_contains($text, 'data-entity-type="file"') || !str_contains($text, 'data-entity-uuid')) {
    return $text;
  }

  // Convert images (Drupal core) and files ('Editor File Upload' module).
  $document = Html::load($text);
  $xpath = new \DOMXPath($document);

  // @todo 'a' not used yet.
  $type = ['img' => 'image', 'a' => 'file'];
  $attributes_query = '[@data-entity-type="file" and @data-entity-uuid]';
  // @todo Should be "//img$attributes_query|//a$attributes_query" when we'll
  // convert also embed files.
  $nodes = $xpath->query("//img$attributes_query");

  /** @var \DOMElement $node */
  foreach ($nodes as $node) {
    // @todo OR `($node->tagName === 'a' && !$node->hasAttribute('href'))` when
    // we'll convert also embed files.
    if ($node->tagName === 'img' && !$node->hasAttribute('src')) {
      // Don't handle malformed images.
      continue;
    }
    $uuid = $node->getAttribute('data-entity-uuid');
    if (!isset($sandbox['fids'][$uuid])) {
      // There's no file entity with this UUID.
      continue;
    }

    $media = $document->createElement('drupal-media');
    if ($node->tagName === 'img') {
      // Always ensure an alt tag, even it's an empty string.
      $media->setAttribute('alt', $node->getAttribute('alt'));
    }
    $media->setAttribute('data-entity-type', 'media');
    $media->setAttribute('data-entity-uuid', _joinup_core_get_media($type[$node->tagName], $sandbox['fids'][$uuid], $uid, $group_id)->uuid());

    if ($imageStyle = $node->getAttribute('data-image-style')) {
      $media->setAttribute('data-view-mode', 'media_library' . substr($imageStyle, 7));
    }
    if ($align = $node->getAttribute('data-align')) {
      $media->setAttribute('data-align', $align);
    }
    if ($caption = $node->getAttribute('data-caption')) {
      $media->setAttribute('data-caption', $caption);
    }

    $node->parentNode->replaceChild($media, $node);
  }

  $text = Html::serialize($document);

  if (str_contains($text, 'data-image-style')) {
    // The data-image-style attribute was accidentally assigned to <a ...>.
    return preg_replace('@<a\s+data-image-style="[^"]*"@', '<a', $text);
  }

  return $text;
}

/**
 * Returns a loaded or created media entity.
 *
 * @param string $media_type
 *   The media bundle.
 * @param mixed $value
 *   The field value.
 * @param int $uid
 *   The entity owner ID.
 * @param string $group_id
 *   The group ID.
 *
 * @return \Drupal\media\MediaInterface
 *   The loaded or created media entity.
 */
function _joinup_core_get_media(string $media_type, mixed $value, int $uid, string $group_id): MediaInterface {
  $media_storage = \Drupal::entityTypeManager()->getStorage('media');

  $media_ids = $media_storage->getQuery()->accessCheck(FALSE)
    ->condition('bundle', $media_type)
    ->condition($media_type, $value)
    ->condition('og_audience', $group_id)
    ->execute();
  if ($media_ids) {
    $media = $media_storage->load(reset($media_ids));
  }
  else {
    $media = $media_storage
      ->create(['bundle' => $media_type, $media_type => $value])
      ->set('og_audience', $group_id);
    if ($uid) {
      $media->setOwnerId($uid);
    }
    $media->save();
  }

  return $media;
}
