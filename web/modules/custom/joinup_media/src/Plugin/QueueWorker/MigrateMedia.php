<?php

declare(strict_types = 1);

namespace Drupal\joinup_media\Plugin\QueueWorker;

use Drupal\Component\Utility\Html;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\Template\Attribute;
use Drupal\collection\Entity\CollectionInterface;
use Drupal\media\MediaInterface;
use Drupal\sparql_entity_storage\Driver\Database\sparql\ConnectionInterface;
use Drupal\sparql_entity_storage\SparqlEntityStorageFieldHandlerInterface;
use Drupal\sparql_entity_storage\SparqlEntityStorageGraphHandlerInterface;
use EasyRdf\Graph;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines 'joinup_media_migrate' queue worker.
 *
 * @QueueWorker(
 *   id = "joinup_media_migrate",
 *   title = @Translation("Migrate videos and images embed markup into media"),
 *   cron = {"time" = 120},
 * )
 */
final class MigrateMedia extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * Constructs a new plugin instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\sparql_entity_storage\Driver\Database\sparql\ConnectionInterface $sparqlEndpoint
   *   The sparql.endpoint service.
   * @param \Drupal\sparql_entity_storage\SparqlEntityStorageFieldHandlerInterface $sparqlFieldHandler
   *   The SPARQL database connection.
   * @param \Drupal\sparql_entity_storage\SparqlEntityStorageGraphHandlerInterface $sparqlGraphHandler
   *   The SPARQL storage field handler service.
   * @param \Drupal\Core\Database\Connection $db
   *   The database connection.x.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected ConnectionInterface $sparqlEndpoint,
    protected SparqlEntityStorageFieldHandlerInterface $sparqlFieldHandler,
    protected SparqlEntityStorageGraphHandlerInterface $sparqlGraphHandler,
    protected Connection $db,
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new self(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('sparql.endpoint'),
      $container->get('sparql.field_handler'),
      $container->get('sparql.graph_handler'),
      $container->get('database'),
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data): void {
    $type = $data['type'];
    $task = $data['task'];
    $fids = $this->db->query("SELECT uuid, fid FROM {file_managed}")->fetchAllKeyed();
    $fields = ['node' => 'body', 'paragraph' => 'field_body'];
    if ($type === 'rdf_entity') {
      $rdf_storage = $this->entityTypeManager->getStorage('rdf_entity');
      [$graph_id, $entity_id] = $task;
      /** @var \Drupal\joinup_group\Entity\GroupContentInterface|\Drupal\collection\Entity\CollectionInterface $entity */
      if ($entity = $rdf_storage->load($entity_id, [$graph_id])) {
        $field_name = $entity->getDescriptionField();
        $text = $entity->get($field_name)->value;
        if (!empty($text)) {
          $group_id = $entity instanceof CollectionInterface ? $entity_id : $entity->getGroupId();
          $processed_text = $this->processText($text, $group_id, (int) $entity->getOwnerId(), $fids);
          if ($processed_text !== $text) {
            $bundle = $entity->bundle();
            $predicate = $this->sparqlFieldHandler->getFieldPredicates($type, $field_name, NULL, $bundle)[$bundle];
            $graph_uri = $this->sparqlGraphHandler->getBundleGraphUri($type, $bundle, $graph_id);
            $graph = new Graph($graph_uri);
            $graph->add($entity_id, $predicate, $processed_text);
            $triples = $graph->serialise('ntriples');
            $query = <<<QUERY
              WITH <$graph_uri>
              DELETE { <$entity_id> <$predicate> ?value }
              INSERT { $triples }
              WHERE { <$entity_id> <$predicate> ?value }
              QUERY;
            $this->sparqlEndpoint->update($query);
          }
        }
      }
    }
    elseif ($type === 'node' || $type === 'paragraph') {
      [$revision_id, $group_id, $is_current_revision, $uid] = $task;
      $field = "$fields[$type]_value";
      $revision_table = "{$type}_revision__$fields[$type]";
      $table = "{$type}__$fields[$type]";
      $text = $this->db->query("SELECT $field FROM {{$revision_table}} WHERE revision_id = $revision_id")->fetchField();
      $processed_text = $this->processText($text, $group_id, $uid, $fids);
      if ($processed_text !== $text) {
        $this->db->update($revision_table)->fields([$field => $processed_text])
          ->condition('revision_id', $revision_id)
          ->execute();
        if ($is_current_revision) {
          $this->db->update($table)->fields([$field => $processed_text])
            ->condition('revision_id', $revision_id)
            ->execute();
        }
      }
    }
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
   * @param array $fids
   *   File entity uuids in system.
   *
   * @return string
   *   Text after has been processed.
   */
  private function processText(string $text, string $group_id, int $uid, array $fids): string {
    $search = $replace = [];

    // Convert embed videos.
    if (str_contains($text, 'preview_thumbnail') && preg_match_all('/(<p>)?(?<json>{(?=.*preview_thumbnail\b)(?=.*settings\b)(?=.*video_url\b)(?=.*settings_summary)(.*)})(<\/p>)?/', $text, $videos_embed)) {
      foreach ($videos_embed['json'] as $delta => $json) {
        // Ensure the JSON string is valid.
        if (($embed_data = json_decode($json, TRUE)) !== NULL) {
          $search[] = $videos_embed[0][$delta];
          $attributes = new Attribute([
            'data-entity-type' => 'media',
            'data-entity-uuid' => $this->getMedia('video', $embed_data['video_url'], $uid, $group_id)->uuid(),
          ]);
          $replace[] = "<drupal-media$attributes />";
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
      if (!isset($fids[$uuid])) {
        // There's no file entity with this UUID.
        continue;
      }

      $media = $document->createElement('drupal-media');
      if ($node->tagName === 'img') {
        // Always ensure an alt tag, even it's an empty string.
        $media->setAttribute('alt', $node->getAttribute('alt'));
      }
      $media->setAttribute('data-entity-type', 'media');
      $media->setAttribute('data-entity-uuid', $this->getMedia($type[$node->tagName], $fids[$uuid], $uid, $group_id)->uuid());

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
  private function getMedia(string $media_type, mixed $value, int $uid, string $group_id): MediaInterface {
    $media_storage = $this->entityTypeManager->getStorage('media');

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

}
