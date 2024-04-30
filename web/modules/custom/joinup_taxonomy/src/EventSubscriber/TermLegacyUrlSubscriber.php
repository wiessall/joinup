<?php

declare(strict_types=1);

namespace Drupal\joinup_taxonomy\EventSubscriber;

use Drupal\Core\Cache\CacheableRedirectResponse;
use Drupal\Core\EventSubscriber\HttpExceptionSubscriberBase;
use Drupal\rdf_sync\RdfSyncMapper;
use Drupal\taxonomy\TermInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;

/**
 * Handles legacy taxonomy terms canonical URLs.
 */
class TermLegacyUrlSubscriber extends HttpExceptionSubscriberBase {

  /**
   * Constructs a new event subscriber instance.
   *
   * @param \Drupal\rdf_sync\RdfSyncMapper $mapper
   *   The RDF Sync mapper service.
   */
  public function __construct(protected RdfSyncMapper $mapper) {}

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
    if ($term = $this->getTerm($event->getRequest()->getPathInfo())) {
      $options = [];
      if ($query = $event->getRequest()->query->all()) {
        $options['query'] = $query;
      }
      $path = $term->toUrl('canonical', $options)->toString();
      $response = (new CacheableRedirectResponse($path))->addCacheableDependency($term);
      $event->setResponse($response);
    }
  }

  /**
   * Tries to get the term from the passed path.
   *
   * @param string $path
   *   The request path.
   *
   * @return \Drupal\taxonomy\TermInterface|null
   *   The term entity or NULL if the request URL doesn't designate a term.
   */
  protected function getTerm(string $path): ?TermInterface {
    // Quick check.
    if (!str_starts_with($path, '/taxonomy/term/http_e_f_f')) {
      return NULL;
    }

    [2 => $encodedUri] = explode('/', trim($path, '/'));
    // With the legacy storage, term IDs were encoded URIs.
    $uri = strtr($encodedUri, self::MAP);

    if (!$term = $this->mapper->getEntityByUri($uri)) {
      return NULL;
    }

    // Make sure is a mapped term.
    return $term instanceof TermInterface ? $term : NULL;
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
