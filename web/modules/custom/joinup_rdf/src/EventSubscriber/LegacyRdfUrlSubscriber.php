<?php

declare(strict_types=1);

namespace Drupal\joinup_rdf\EventSubscriber;

use Drupal\Core\Cache\CacheableRedirectResponse;
use Drupal\Core\EventSubscriber\HttpExceptionSubscriberBase;
use Drupal\joinup_rdf\Entity\RdfSyncEntityInterface;
use Drupal\rdf_sync\RdfSyncMapper;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;

/**
 * Handles legacy RDF entity canonical URLs.
 */
class LegacyRdfUrlSubscriber extends HttpExceptionSubscriberBase {

  public function __construct(protected RdfSyncMapper $mapper) {}

  /**
   * {@inheritdoc}
   */
  protected function getHandledFormats(): array {
    // Act on any format.
    return [];
  }

  /**
   * Redirects legacy entity URL to the actual term canonical page.
   *
   * @param \Symfony\Component\HttpKernel\Event\ExceptionEvent $event
   *   The request event object.
   */
  public function on404(ExceptionEvent $event): void {
    $request = $event->getRequest();
    [$entity, $suffix] = $this->getTargetEntity($request->getPathInfo());
    if (!$entity) {
      return;
    }
    assert($entity instanceof RdfSyncEntityInterface);

    $path = http_build_url([
      'path' => $entity->toUrl('canonical')->toString() . $suffix,
      'query' => http_build_query($request->query->all()),
    ]);

    $response = (new CacheableRedirectResponse($path))->addCacheableDependency($entity);
    $event->setResponse($response);
  }

  /**
   * Returns a tuple consisting from the target entity and path suffix.
   *
   * @param string $path
   *   The legacy path.
   *
   * @return array
   *   The target entity and a potential URL suffix (subdir):
   *     - 0: Entity or NULL,
   *     - 1: Suffix as string.
   */
  public function getTargetEntity(string $path): array {
    // Extracts the encoded URI and everything that comes after.
    $pattern = '@^(?<prefix>/rdf_entity/|/taxonomy/term/)(?<uri>http_e_f_f[^/]*)(?<suffix>/(?:.*))?$@';
    if (!preg_match($pattern, $path, $found)) {
      return [NULL, NULL];
    }

    if (!$entity = $this->getEntity($found['uri'])) {
      return [NULL, NULL];
    }

    return [$entity, $found['suffix'] ?? ''];
  }

  /**
   * Tries to get the entity from its encoded URI.
   *
   * @param string $encodedUri
   *   The entity encoded URI path.
   *
   * @return \Drupal\joinup_rdf\Entity\RdfSyncEntityInterface|null
   *   The entity or NULL if the request URL doesn't designate a mapped entity.
   */
  protected function getEntity(string $encodedUri): ?RdfSyncEntityInterface {
    // With the legacy storage, RDF entity IDs were encoded URIs.
    $uri = strtr($encodedUri, self::MAP);

    if (!$entity = $this->mapper->getEntityByUri($uri)) {
      return NULL;
    }

    // Make sure is a mapped entity.
    return $entity instanceof RdfSyncEntityInterface ? $entity : NULL;
  }

  /**
   * Map used to decode the URI.
   *
   * @const string[]
   *
   * @see https://git.drupalcode.org/project/sparql_entity_storage/-/blob/HEAD/src/UriEncoder.php
   */
  private const MAP = [
    '_a' => '_',
    '_b' => '-',
    '_c' => '.',
    '_d' => '~',
    '_e' => ':',
    '_f' => '/',
    '_g' => '?',
    '_h' => '#',
    '_i' => '[',
    '_j' => ']',
    '_k' => '@',
    '_l' => '!',
    '_m' => '$',
    '_n' => '&',
    '_o' => "'",
    '_p' => '(',
    '_q' => ')',
    '_r' => '*',
    '_s' => '+',
    '_t' => ',',
    '_u' => ';',
    '_v' => '=',
    '_w' => '`',
    '_x' => '%',
  ];

  /**
   * Conversion for path prefix.
   */
  private const array PREFIX = [
    '/rdf_entity/' => '/node/',
    '/taxonomy/term/' => '/taxonomy/term/',
  ];

}
