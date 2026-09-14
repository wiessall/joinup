<?php

namespace Drupal\ai_output_checks\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use GuzzleHttp\ClientInterface;

final class UrlChecker {

  public function __construct(
    private readonly ClientInterface $httpClient,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly string $configName = 'ai_output_checks.settings',
  ) {}

  public function check(string $url): UrlCheckResult {
    $url = trim($url);
    if ($url === '') {
      return new UrlCheckResult(FALSE, NULL, 'empty');
    }

    // Basic validation.
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
      return new UrlCheckResult(FALSE, NULL, 'invalid_url');
    }

    $config = $this->configFactory->get($this->configName);
    $timeout = (float) ($config->get('url_check_timeout') ?? 30);
    $method  = (string) ($config->get('url_check_method') ?? 'HEAD');

    try {
      $resp = $this->httpClient->request($method, $url, [
        'timeout' => $timeout,
        'connect_timeout' => $timeout,
        'allow_redirects' => TRUE,
        'http_errors' => FALSE,
        // Optional: set a UA; some sites block default.
        'headers' => [
          'User-Agent' => 'ai_output_checks/1.0 (+Drupal)',
        ],
      ]);

      $code = $resp->getStatusCode();
      $ok = ($code >= 200 && $code < 400);
      return new UrlCheckResult($ok, $code, 'http_status');
    }
    catch (\Throwable $e) {
      // You can log $e->getMessage() if you want.
      return new UrlCheckResult(FALSE, NULL, 'exception');
    }
  }

}
