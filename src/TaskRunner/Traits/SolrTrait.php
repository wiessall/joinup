<?php

declare(strict_types = 1);

namespace Joinup\TaskRunner\Traits;

use GuzzleHttp\Client;
use Psr\Http\Message\ResponseInterface;
use Robo\Exception\AbortTasksException;

trait SolrTrait {

  /**
   * The cached HTTP client.
   *
   * @var \GuzzleHttp\Client
   */
  protected $httpClient;

  /**
   * Performs an HTTP get request.
   *
   * @param string $url
   *   The HTTP get request URL.
   *
   * @return \Psr\Http\Message\ResponseInterface
   *   The HTTP response
   *
   * @throws \Robo\Exception\AbortTasksException
   *   When the Solr server response is invalid.
   */
  protected function httpGet(string $url): ResponseInterface {
    if (!isset($this->httpClient)) {
      $this->httpClient = new Client();
    }

    try {
      $response = $this->httpClient->get($url);
    }
    catch (\Exception $exception) {
      throw new AbortTasksException($exception->getMessage(), 0, $exception);
    }
    if ($response->getStatusCode() != 200) {
      throw new AbortTasksException("Solr server returned HTTP code {$response->getStatusCode()}, while querying {$url}.");
    }

    return $response;
  }

}
