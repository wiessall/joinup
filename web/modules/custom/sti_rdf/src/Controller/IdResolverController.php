<?php

declare(strict_types=1);

namespace Drupal\sti_rdf\Controller;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Http\Exception\CacheableNotFoundHttpException;
use Drupal\Core\Routing\LocalRedirectResponse;
use Drupal\Core\Url;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\rdf_sync\RdfSyncMapper;
use Drupal\sti_rdf\Event\UriResolveEvent;
use Drupal\sti_rdf\StiUri;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Dereferences https://sti-portal.fao.org/id/{type}/{uuid}.
 *
 * An instance URI names a thing (an innovation, a source record), not a
 * page, so the answer is 303 See Other to the page that describes it. A
 * superseded resource answers 301 to the URI that replaced it. Anything the
 * visitor may not view answers 404, never 403, so that the resolver does not
 * confirm the existence of unpublished records.
 */
final class IdResolverController implements ContainerInjectionInterface {

  public function __construct(
    private readonly RdfSyncMapper $mapper,
    private readonly EventDispatcherInterface $eventDispatcher,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('rdf_sync.mapper'),
      $container->get('event_dispatcher'),
    );
  }

  /**
   * Redirects an instance URI to its document or its replacement.
   */
  public function resolve(string $type, string $uuid): LocalRedirectResponse {
    // Whether a URI resolves depends on rdf_sync_uri and on subscribers,
    // neither of which carries a cache tag, so misses are never cached.
    $uncacheable = (new CacheableMetadata())->setCacheMaxAge(0);
    try {
      $uri = StiUri::forEntity($type, $uuid);
    }
    catch (\InvalidArgumentException) {
      throw new CacheableNotFoundHttpException($uncacheable);
    }

    $event = new UriResolveEvent($uri);
    $this->eventDispatcher->dispatch($event);
    $replacement = $event->getReplacementUri();
    if ($replacement !== NULL) {
      $parts = StiUri::parse($replacement);
      assert($parts !== NULL);
      $generated = Url::fromRoute('sti_rdf.id', $parts)->toString(TRUE);
      $response = new LocalRedirectResponse($generated->getGeneratedUrl(), 301);
      $response->addCacheableDependency($generated);
      $response->addCacheableDependency($uncacheable);
      return $response;
    }

    $entity = $this->mapper->getEntityByUri($uri);
    if (!$entity instanceof ContentEntityInterface) {
      throw new CacheableNotFoundHttpException($uncacheable);
    }

    $document = $this->documentEntity($entity);
    $access = $document->access('view', NULL, TRUE);
    $cacheability = CacheableMetadata::createFromObject($access)
      ->addCacheableDependency($document)
      ->addCacheableDependency($entity);
    if (!$access->isAllowed() || !$document->hasLinkTemplate('canonical')) {
      throw new CacheableNotFoundHttpException($cacheability);
    }

    $url = $document->toUrl('canonical');
    if ($document !== $entity) {
      $url->setOption('fragment', $entity->getEntityTypeId() . '-' . $entity->id());
    }

    $generated = $url->toString(TRUE);
    $response = new LocalRedirectResponse($generated->getGeneratedUrl(), 303);
    $response->addCacheableDependency($cacheability);
    $response->addCacheableDependency($generated);
    return $response;
  }

  /**
   * Returns the entity whose page describes the given one.
   *
   * Paragraphs (claims, claim resolutions) have no page of their own; they
   * are described on their host. Nested paragraphs are walked up to the
   * first non-paragraph host.
   */
  private function documentEntity(EntityInterface $entity): EntityInterface {
    $current = $entity;
    $guard = 0;
    while ($current instanceof ParagraphInterface && $guard++ < 10) {
      $parent = $current->getParentEntity();
      if ($parent === NULL) {
        throw new CacheableNotFoundHttpException((new CacheableMetadata())->addCacheableDependency($current));
      }
      $current = $parent;
    }
    return $current;
  }

}
