<?php

declare(strict_types=1);

namespace Drupal\sparql_endpoint_piwik;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Session\AccountProxyInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\RequestOptions;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;

/**
 * Track usage of the SPARQL endpoint.
 */
readonly class EndpointTracker {

  private const string CONFIG_NAME = 'sparql_endpoint_piwik.settings';

  public function __construct(
    protected ClientInterface $httpClient,
    protected AccountProxyInterface $currentUser,
    #[Autowire(service: 'logger.channel.sparql_endpoint')]
    protected LoggerChannelInterface $logger,
    protected ConfigFactoryInterface $configFactory,
  ) {
  }

  /**
   * Tracks the request by sending a GET request.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   */
  public function track(Request $request): void {
    $url = $this->getUrl();
    if (!$url) {
      $this->logger->error('PIWIK base URI is not configured.');
      return;
    }

    $this->httpClient->requestAsync(
      'GET', $url,
      [
        RequestOptions::QUERY => $this->buildQuery($request),
        RequestOptions::HEADERS => $this->buildHeaders($request),
      ]
    )
      ->then(NULL, function () use ($url): void {
        $this->logger->error('Can\'t send track data to @url',
          ['@url' => $url]
        );
      })
      ->wait();
  }

  /**
   * Builds the query parameters for the request.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request stack.
   *
   * @return array
   *   The query parameters.
   *
   * @see https://developers.piwik.pro/en/latest/data_collection/api/http_api.html
   */
  protected function buildQuery(Request $request): array {
    $config = $this->configFactory->get(self::CONFIG_NAME);
    $query = [
      'idsite' => $config->get('site_id'),
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
   * @param \Symfony\Component\HttpFoundation\Request $request
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
   * @return string|null
   *   The endpoint URL.
   */
  protected function getUrl(): ?string {
    $config = $this->configFactory->get(self::CONFIG_NAME);
    $url = $config->get('base_uri');
    if (empty($url)) {
      return NULL;
    }

    if (!preg_match("~^(?:f|ht)tps?://~i", $url)) {
      $url = 'https://' . $url;
    }

    return http_build_url($url, ['path' => 'ppms.php']);
  }

}
