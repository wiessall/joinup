<?php

declare(strict_types = 1);

namespace Joinup\TaskRunner\Traits;

use GuzzleHttp\Client;
use Psr\Http\Message\ResponseInterface;
use Robo\Exception\AbortTasksException;

/**
 * A trait with helper methods related to Solr instance.
 */
trait SolrTrait {

  /**
   * The cached HTTP client.
   *
   * @var \GuzzleHttp\Client
   */
  protected Client $httpClient;

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
    try {
      $response = $this->getHttpClient()->get($url);
    }
    catch (\Exception $exception) {
      throw new AbortTasksException($exception->getMessage(), 0, $exception);
    }
    if ($response->getStatusCode() != 200) {
      throw new AbortTasksException("Solr server returned HTTP code {$response->getStatusCode()}, while querying {$url}.");
    }

    return $response;
  }

  /**
   * Performs an HTTP post request.
   *
   * @param string $url
   *   The HTTP request URL.
   * @param string $body
   *   The POST body.
   * @param array $headers
   *   (optional) A list of headers.
   *
   * @return \Psr\Http\Message\ResponseInterface
   *   The HTTP response
   *
   * @throws \Robo\Exception\AbortTasksException
   *   When the Solr server response is invalid.
   */
  protected function httpPost(string $url, string $body, array $headers = []): ResponseInterface {
    try {
      $response = $this->getHttpClient()->post($url, [
        'body' => $body,
        'headers' => $headers,
      ]);
    }
    catch (\Exception $exception) {
      throw new AbortTasksException($exception->getMessage(), 0, $exception);
    }
    if ($response->getStatusCode() != 200) {
      throw new AbortTasksException("Solr server returned HTTP code {$response->getStatusCode()}, while querying {$url}.");
    }

    return $response;
  }

  /**
   * Returns the HTTP client.
   *
   * @return \GuzzleHttp\Client
   *   The HTTP client.
   */
  protected function getHttpClient(): Client {
    if (!isset($this->httpClient)) {
      $this->httpClient = new Client();
    }
    return $this->httpClient;
  }

  /**
   * Returns the SOLR URL including the path.
   *
   * @return string
   *   The URL of SOLR endpoint, including the path.
   */
  public static function getSolrUrl(): string {
    return http_build_url([
      'scheme' => getenv('SEARCH_API_SERVER_SS_SOLR_SCHEME') ?: 'http',
      'host' => getenv('SEARCH_API_SERVER_SS_SOLR_HOST'),
      'port' => getenv('SEARCH_API_SERVER_SS_SOLR_PORT'),
      'path' => 'solr',
    ]);
  }

}
