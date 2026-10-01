<?php

namespace Drupal\sti_portal_api\Plugin\rest\resource;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileUrlGeneratorInterface;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Returns the published solutions belonging to one collection.
 *
 * One endpoint, every collection. The response shape is a flat array of
 * objects, deliberately matching what a Views REST export would emit, so
 * consumers can be pointed here without changing their parsing.
 *
 * @RestResource(
 *   id = "sti_collection_solutions",
 *   label = @Translation("STI collection solutions"),
 *   uri_paths = {
 *     "canonical" = "/api/collection-solutions/v1/{collection}"
 *   }
 * )
 */
class CollectionSolutionsResource extends ResourceBase {

  /**
   * Content type holding solutions.
   */
  private const SOLUTION_BUNDLE = 'solution';

  /**
   * Group audience field on the solution node.
   */
  private const AUDIENCE_FIELD = 'og_audience';

  /**
   * Image field on the solution node. Set to NULL if there is none.
   */
  private const LOGO_FIELD = 'logo';

  /**
   * Content type holding collections.
   */
  private const COLLECTION_BUNDLE = 'collection';

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    array $serializer_formats,
    LoggerInterface $logger,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected FileUrlGeneratorInterface $fileUrlGenerator,
    protected Connection $database,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->getParameter('serializer.formats'),
      $container->get('logger.factory')->get('sti_portal_api'),
      $container->get('entity_type.manager'),
      $container->get('file_url_generator'),
      $container->get('database'),
    );
  }

  /**
   * GET /api/collection-solutions/v1/{collection}
   *
   * {collection} accepts a node ID, a UUID, or a path-alias segment.
   */
  public function get(string $collection): ResourceResponse {
    $collectionNode = $this->loadCollection($collection);
    if (!$collectionNode) {
      throw new NotFoundHttpException(sprintf('Collection "%s" not found.', $collection));
    }

    $storage = $this->entityTypeManager->getStorage('node');
    $ids = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', self::SOLUTION_BUNDLE)
      ->condition('status', 1)
      ->condition(self::AUDIENCE_FIELD . '.target_id', $collectionNode->id())
      ->sort('title', 'ASC')
      ->range(0, 200)
      ->execute();

    $uris = $this->graphUris($ids);
    $rows = [];
    foreach ($storage->loadMultiple($ids) as $node) {
      $rows[] = [
        'nid'      => (int) $node->id(),
        'uuid'     => $node->uuid(),
        // The subject of this solution's triples in the graph, so a client
        // can go from this list straight to SPARQL. NULL until first synced.
        'uri'      => $uris[(int) $node->id()] ?? NULL,
        'title'    => $node->label(),
        'abstract' => $this->abstractOf($node),
        'logo'     => $this->logoOf($node),
        'path'     => [['alias' => $node->toUrl()->toString()]],
      ];
    }

    $response = new ResourceResponse($rows, 200);

    // Invalidate whenever any node changes; narrow this to the collection's
    // own tag once the traffic justifies it.
    $cache = new CacheableMetadata();
    $cache->addCacheTags(['node_list:' . self::SOLUTION_BUNDLE]);
    $cache->addCacheContexts(['user.permissions', 'url.path']);
    $cache->setCacheMaxAge(300);
    $response->addCacheableDependency($cache);

    return $response;
  }

  /**
   * Reads the URIs rdf_sync stored for the given nodes.
   *
   * Read from rdf_sync_uri rather than computed from the UUID: solutions
   * synced before the P0 URI pattern keep their earlier URI until re-minted.
   *
   * @param array $nids
   *   Node ids.
   *
   * @return array<int, string>
   *   URI keyed by node id.
   */
  private function graphUris(array $nids): array {
    if (!$nids || !$this->database->schema()->tableExists('rdf_sync_uri')) {
      return [];
    }
    return array_map('strval', $this->database->select('rdf_sync_uri', 'u')
      ->fields('u', ['entity_id', 'uri'])
      ->condition('u.entity_type', 'node')
      ->condition('u.entity_id', array_values($nids), 'IN')
      ->execute()
      ->fetchAllKeyed());
  }

  /**
   * Resolves a collection from an ID, UUID or alias segment.
   */
  private function loadCollection(string $identifier) {
    $storage = $this->entityTypeManager->getStorage('node');

    if (ctype_digit($identifier)) {
      $node = $storage->load($identifier);
      return ($node && $node->bundle() === self::COLLECTION_BUNDLE && $node->access('view')) ? $node : NULL;
    }

    if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $identifier)) {
      $found = $storage->loadByProperties(['uuid' => $identifier, 'type' => self::COLLECTION_BUNDLE]);
      $node = $found ? reset($found) : NULL;
      return ($node && $node->access('view')) ? $node : NULL;
    }

    // Path alias, e.g. "fao-dvi" -> /collection/fao-dvi.
    $path = \Drupal::service('path_alias.manager')
      ->getPathByAlias('/collection/' . $identifier);
    if (preg_match('#^/node/(\d+)$#', $path, $m)) {
      $node = $storage->load($m[1]);
      return ($node && $node->access('view')) ? $node : NULL;
    }

    return NULL;
  }

  private function abstractOf($node): ?string {
    foreach (['abstract', 'description', 'body'] as $field) {
      if (!$node->hasField($field) || $node->get($field)->isEmpty()) {
        continue;
      }
      $item = $node->get($field)->first()->getValue();
      $text = $item['summary'] ?? $item['value'] ?? NULL;
      if (!$text) {
        continue;
      }
      $clean = trim(strip_tags($text));
      return mb_strlen($clean) > 300 ? mb_substr($clean, 0, 300) . '…' : $clean;
    }
    return NULL;
  }

  private function logoOf($node): array {
    $field = self::LOGO_FIELD;
    if (!$field || !$node->hasField($field) || $node->get($field)->isEmpty()) {
      return [];
    }
    $target = $node->get($field)->entity;
    if (!$target) {
      return [];
    }

    // Media entity: step through to the file it wraps.
    if ($target->getEntityTypeId() === 'media') {
      $source = $target->getSource();
      $fid = $source ? $target->get($source->getConfiguration()['source_field'] ?? '')->target_id : NULL;
      $target = $fid ? $this->entityTypeManager->getStorage('file')->load($fid) : NULL;
    }

    if (!$target || $target->getEntityTypeId() !== 'file') {
      return [];
    }

    return [[
      'url' => $this->fileUrlGenerator->generateAbsoluteString($target->getFileUri()),
    ]];
  }

}
