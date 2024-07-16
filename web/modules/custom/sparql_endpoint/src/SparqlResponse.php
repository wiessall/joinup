<?php

declare(strict_types=1);

namespace Drupal\sparql_endpoint;

/**
 * Represents a SPARQL response.
 */
readonly class SparqlResponse {

  public function __construct(
    public string|int $code,
    public string $results,
  ) {}

  /**
   * Checks if the code is equal to '200' indicating a successful operation.
   *
   * @return bool
   *   Returns true if the code is equal to '200', false otherwise.
   */
  public function isSuccess(): bool {
    return $this->code === 200;
  }

}
