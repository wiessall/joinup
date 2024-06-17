<?php

declare(strict_types=1);

namespace Drupal\sparql_endpoint_piwik;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Session\AccountProxyInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\RequestOptions;
use Symfony\Component\HttpFoundation\Request;

/**
 * Track usage of the SPARQL endpoint.
 */
class EndpointTracker {

  /**
   * Module configuration.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected ImmutableConfig $config;

  public function __construct(
    protected ClientInterface $httpClient,
    protected AccountProxyInterface $currentUser,
    ConfigFactoryInterface $configFactory,
  ) {
    $this->config = $configFactory->get('sparql_endpoint_piwik.settings');
  }

  /**
   * Tracks the request by sending a GET request to the URL with the query
   * parameters.
   *
   * @param Request $request
   *   The request.
   */
  public function track(Request $request): void {
    $this->httpClient->requestAsync(
      'GET',
      $this->getUrl(),
      [
        RequestOptions::QUERY => $this->buildQuery($request),
        RequestOptions::HEADERS => $this->buildHeaders($request),
      ]
    )->wait();
  }

  /**
   * Builds the query parameters for the request.
   *
   * @param Request $request
   *   The request stack.
   *
   * @return array
   *   The query parameters.
   *
   * @see https://developers.piwik.pro/en/latest/data_collection/api/http_api.html
   */
  protected function buildQuery(Request $request): array {
    $query = [
      'idsite' => $this->config->get('site_id'),
      'rec' => 1,
      'url' => $request->getUri(),
      'action_name' => 'sparql_endpoint',
      'r' => microtime(TRUE),
      'uid' => $this->currentUser->id(),
    ];

    $referer = $request->headers->get('referer');
    if ($referer) {
      $query['urlref'] = $referer;
    }

    return $query;
  }

  /**
   * Builds the headers for the request.
   *
   * @param Request $request
   *   The request stack.
   *
   * @return array
   *   The headers.
   */
  protected function buildHeaders(Request $request): array {
    return [
      'User-Agent' => $request->headers->get(
        'User-Agent',
        'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36'
      ),
    ];
  }

  /**
   * Returns the PIWIK endpoint URL.
   *
   * @return string
   *   The endpoint URL.
   */
  protected function getUrl(): string {
    $url = $this->config->get('base_uri');

    return http_build_url($url, ['path' => 'ppms.php']);
  }

}


