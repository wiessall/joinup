<?php

declare(strict_types=1);

namespace Drupal\sparql_endpoint;

use Psr\Http\Message\ResponseInterface;

/**
 * Provides an interface for SPARQL read-only endpoint service.
 */
interface SparqlReadOnlyEndpointInterface {

  /**
   * Performs an HTTP query against the endpoint.
   *
   * @param string $query
   *   The SPARQL query.
   * @param string|null $accept
   *   (optional) The 'Accept' HTTP header. This is used to determine the format
   *   of the response.
   *
   * @return \Psr\Http\Message\ResponseInterface
   *   The HTTP response.
   */
  public function query(string $query, ?string $accept = NULL): ResponseInterface;

}
