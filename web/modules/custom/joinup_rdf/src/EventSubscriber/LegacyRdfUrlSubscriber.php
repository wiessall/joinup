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

  /**
   * The RDF Sync mapper service.
   *
   * @var \Drupal\rdf_sync\RdfSyncMapper|null
   */
  protected ?RdfSyncMapper $mapper = NULL;

  /**
   * Constructs a new event subscriber instance.
   *
   * @param \Drupal\rdf_sync\RdfSyncMapper $mapper
   *   The RDF Sync mapper service.
   */
  public function setRdfSyncMapper(RdfSyncMapper $mapper) {
    $this->mapper = $mapper;
  }

  /**
   * {@inheritdoc}
   */
  protected function getHandledFormats(): array {
    // Act on any format.
    return [];
  }

  /**
   * Redirects legacy term URL to the actual term canonical page.
   *
   * @param \Symfony\Component\HttpKernel\Event\ExceptionEvent $event
   *   The request event object.
   */
  public function on404(ExceptionEvent $event): void {
    if ($entity = $this->getEntity($event->getRequest()->getPathInfo())) {
      $options = [];
      if ($query = $event->getRequest()->query->all()) {
        $options['query'] = $query;
      }
      $path = $entity->toUrl('canonical', $options)->toString();
      $response = (new CacheableRedirectResponse($path))->addCacheableDependency($entity);
      $event->setResponse($response);
    }
  }

  /**
   * Tries to get the term from the passed path.
   *
   * @param string $path
   *   The request path.
   *
   * @return \Drupal\joinup_rdf\Entity\RdfSyncEntityInterface|null
   *   The term entity or NULL if the request URL doesn't designate a term.
   */
  protected function getEntity(string $path): ?RdfSyncEntityInterface {
    // Quick check.
    if (
      !$this->mapper ||
      !str_contains($path, '/http_e_f_f')
    ) {
      return NULL;
    }

    // Path might be either "/rdf_entity/<encoded id>" or
    // "/taxonomy/term/<encoded id>".
    $pathAfterEncodedUriScheme = 'http_e_f_f' . explode('/http_e_f_f', $path)[1];
    $encodedUri = explode('/', $pathAfterEncodedUriScheme)[0];
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

}
