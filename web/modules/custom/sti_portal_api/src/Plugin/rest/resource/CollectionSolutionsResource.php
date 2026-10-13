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
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Returns the content belonging to one collection.
 *
 * Query parameters, all optional:
 * - ?type=source_record,atio_innovation  Which content types; comma-separated.
 *   Default: both. Public names, mapped to the bundles in TYPES.
 * - ?moderation=published|unpublished|all  Published status. Default:
 *   published. Unpublished rows are only returned to users who may view them.
 * - ?limit=N (1 to 500) and ?page=P (from 0)  Page through the result.
 *   Default: no limit, everything is returned.
 *
 * An unknown type or moderation value is a 400, not an empty list, so a typo
 * in a client never looks like an empty collection.
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
   * Public type name => node bundle.
   */
  private const TYPES = [
    'source_record' => 'solution',
    'atio_innovation' => 'atio_innovation',
  ];

  /**
   * Accepted ?moderation values.
   */
  private const MODERATION = ['published', 'unpublished', 'all'];

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

  /**
   * Workflow state field on the solution node.
   */
  private const STATE_FIELD = 'field_state';

  /**
   * Upper bound for ?limit.
   */
  private const MAX_LIMIT = 500;

  /**
   * Nodes loaded at a time when building the response.
   */
  private const LOAD_CHUNK = 100;

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    array $serializer_formats,
    LoggerInterface $logger,
    protected EntityTypeManagerInterface $entityTypeManager,
    protected FileUrlGeneratorInterface $fileUrlGenerator,
    protected Connection $database,
    protected RequestStack $requestStack,
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
      $container->get('request_stack'),
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

    $query = $this->requestStack->getCurrentRequest()?->query;
    $bundles = $this->requestedBundles($query?->get('type'));
    $moderation = $query?->get('moderation') ?? 'published';
    if (!in_array($moderation, self::MODERATION, TRUE)) {
      throw new BadRequestHttpException(sprintf('Unknown moderation "%s"; use one of: %s.', $moderation, implode(', ', self::MODERATION)));
    }

    $storage = $this->entityTypeManager->getStorage('node');
    $entityQuery = $storage->getQuery()
      ->accessCheck(TRUE)
      ->condition('type', array_values($bundles), 'IN')
      ->condition(self::AUDIENCE_FIELD . '.target_id', $collectionNode->id())
      ->sort('title', 'ASC')
      ->sort('nid', 'ASC');
    if ($moderation === 'published') {
      $entityQuery->condition('status', 1);
    }
    elseif ($moderation === 'unpublished') {
      $entityQuery->condition('status', 0);
    }
    if ($query?->has('limit')) {
      $limit = min(self::MAX_LIMIT, max(1, (int) $query->get('limit')));
      $page = max(0, (int) ($query->get('page') ?? 0));
      $entityQuery->range($page * $limit, $limit);
    }
    $ids = array_values($entityQuery->execute());

    $types = array_flip(self::TYPES);
    $uris = $this->graphUris($ids);
    $rows = [];
    foreach (array_chunk($ids, self::LOAD_CHUNK) as $chunk) {
      foreach ($storage->loadMultiple($chunk) as $node) {
        // The query's access check does not hide unpublished nodes on a site
        // without node grants, so each unpublished one is checked on its own.
        // A page can therefore hold fewer rows than the limit.
        if (!$node->isPublished() && !$node->access('view')) {
          continue;
        }
        $rows[] = [
          'nid'       => (int) $node->id(),
          'uuid'      => $node->uuid(),
          'type'      => $types[$node->bundle()],
          'published' => $node->isPublished(),
          // The workflow state; NULL where the bundle has no state field.
          'state'     => $node->hasField(self::STATE_FIELD) ? $node->get(self::STATE_FIELD)->value : NULL,
          // The subject of this node's triples in the graph, so a client can
          // go from this list straight to SPARQL. NULL until first synced.
          'uri'       => $uris[(int) $node->id()] ?? NULL,
          'title'     => $node->label(),
          'abstract'  => $this->abstractOf($node),
          'logo'      => $this->logoOf($node),
          'path'      => [['alias' => $node->toUrl()->toString()]],
        ];
      }
      // Without this a full collection stays in the entity static cache.
      $storage->resetCache($chunk);
    }

    $response = new ResourceResponse($rows, 200);

    // Invalidate whenever any node changes; narrow this to the collection's
    // own tag once the traffic justifies it.
    $cache = new CacheableMetadata();
    foreach ($bundles as $bundle) {
      $cache->addCacheTags(['node_list:' . $bundle]);
    }
    // What is listed depends on the query string and, once unpublished
    // nodes are included, on who is asking.
    $cache->addCacheContexts(['user.permissions', 'url.path', 'url.query_args']);
    if ($moderation !== 'published') {
      $cache->addCacheContexts(['user']);
    }
    $cache->setCacheMaxAge(300);
    $response->addCacheableDependency($cache);

    return $response;
  }

  /**
   * Turns ?type into bundles.
   *
   * @return array<string, string>
   *   Bundle keyed by public type name.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\BadRequestHttpException
   *   For an unknown type name.
   */
  private function requestedBundles(?string $type): array {
    if ($type === NULL || trim($type) === '') {
      return self::TYPES;
    }
    $bundles = [];
    foreach (array_filter(array_map('trim', explode(',', $type))) as $name) {
      if (!isset(self::TYPES[$name])) {
        throw new BadRequestHttpException(sprintf('Unknown type "%s"; use one of: %s.', $name, implode(', ', array_keys(self::TYPES))));
      }
      $bundles[$name] = self::TYPES[$name];
    }
    // "?type=," names nothing; treat it like no filter.
    return $bundles ?: self::TYPES;
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
