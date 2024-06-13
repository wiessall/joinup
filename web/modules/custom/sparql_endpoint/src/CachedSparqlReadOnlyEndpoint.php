<?php

declare(strict_types=1);

namespace Drupal\sparql_endpoint;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\CacheBackendInterface;

/**
 * Represents a cached SPARQL read-only endpoint.
 */
class CachedSparqlReadOnlyEndpoint {

  public function __construct(
    protected SparqlReadOnlyEndpointInterface $sparqlReadOnlyEndpoint,
    protected CacheBackendInterface $cacheBackend,
    protected TimeInterface $time,
    protected int $ttl,
  ) {}

  /**
   * Executes a SPARQL query.
   *
   * @param string $query
   *   The SPARQL query to execute.
   * @param string|null $accept
   *   The MIME type of the response (optional).
   *
   * @return SparqlResponse
   *   The response of the query.
   */
  public function query(string $query, ?string $accept = NULL): SparqlResponse {
    $cid = 'sparql-data:' . sha1(serialize(func_get_args()));

    if ($cache = $this->cacheBackend->get($cid)) {
      $data = unserialize($cache->data, ['allowed_classes' => [SparqlResponse::class]]);
    }
    else {
      $data = $this->getSparqlResponse($query, $accept);
      $this->cacheBackend->set($cid, serialize($data), $this->time->getRequestTime() + $this->ttl, ['sparql-endpoint-data']);
    }

    return $data;
  }

  /**
   * Retrieves a SPARQL response.
   *
   * @param string $query
   *   The SPARQL query to execute.
   * @param string|null $accept
   *   The MIME type of the response (optional).
   *
   * @return SparqlResponse
   *   The response of the query.
   */
  protected function getSparqlResponse(string $query, ?string $accept = NULL): SparqlResponse {
    try {
      $endpointResponse = $this->sparqlReadOnlyEndpoint->query($query, $accept);
    }
    catch (\Exception $e) {
      return new SparqlResponse(
        $e->getCode(),
        $e->getResponse()->getBody()->getContents()
      );
    }

    return new SparqlResponse(
      $endpointResponse->getStatusCode(),
      $endpointResponse->getBody()->getContents()
    );
  }

}
