<?php

declare(strict_types=1);

namespace Drupal\eu_oss_catalogue_test\Plugin\ServiceMock;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\Serialization\Yaml;
use Drupal\http_request_mock\ServiceMockPluginInterface;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;

/**
 * Mocks requests made to Bitbucket API.
 *
 * @ServiceMock(
 *   id = "eu_oss_catalogue_test_bitbucket",
 *   label = @Translation("API Guesser Bitbucket Mock"),
 *   weight = 10,
 * )
 */
class Bitbucket extends PluginBase implements ServiceMockPluginInterface {

  /**
   * Static cache for response fixture.
   */
  private static ?array $fixture;

  /**
   * {@inheritdoc}
   */
  public function applies(RequestInterface $request, array $options): bool {
    return $this->apiRequestMatches($request);
  }

  /**
   * Determines whether this plugin can handle the incoming request or not.
   *
   * @param \Psr\Http\Message\RequestInterface $request
   *   The HTTP request.
   *
   * @return bool
   *   Whether this plugin can handle the incoming request or not.
   */
  protected function apiRequestMatches(RequestInterface $request): bool {
    $request_uri = $request->getUri();
    if ($request->getMethod() !== 'GET' || $request_uri->getScheme() !== 'https') {
      return FALSE;
    }

    $root = $this->getRepoRootFromRequestUri($request_uri);
    $namespace = $this->getNamespaceFromRequestUri($request_uri);
    $package = $this->getPackageFromRequestUri($request_uri);

    return !empty($namespace) &&
      !empty($package) &&
      array_key_exists($package, self::getFixture()[$root][$namespace] ?? []);
  }

  /**
   * Returns the repository root from the given URI.
   *
   * @param \Psr\Http\Message\UriInterface $uri
   *   The request URI.
   *
   * @return string
   *   The repository root from the given URI.
   */
  protected function getRepoRootFromRequestUri(UriInterface $uri): string {
    $path_parts = explode('/api/2.0/repositories/', $uri->getPath(), 2);
    return $uri->getHost() . $path_parts[0];
  }

  /**
   * Returns the repository namespace from the given URI.
   *
   * @param \Psr\Http\Message\UriInterface $uri
   *   The request URI.
   *
   * @return string
   *   The repository namespace from the given URI.
   */
  protected function getNamespaceFromRequestUri(UriInterface $uri): ?string {
    $path_parts = explode('/api/2.0/repositories/', $uri->getPath(), 2);
    return explode('/', urldecode($path_parts[1] ?? ''))[0] ?? NULL;
  }

  /**
   * Returns the package name from the given URI.
   *
   * @param \Psr\Http\Message\UriInterface $uri
   *   The request URI.
   *
   * @return string
   *   The package name from the given URI.
   */
  protected function getPackageFromRequestUri(UriInterface $uri): ?string {
    $path_parts = explode('/api/2.0/repositories/', $uri->getPath(), 2);
    return explode('/', urldecode($path_parts[1] ?? ''))[1] ?? NULL;
  }

  /**
   * Returns the request fixture array.
   *
   * @return array
   *   The request fixture array.
   */
  protected static function getFixture(): array {
    if (isset(self::$fixture)) {
      return self::$fixture;
    }

    $filePath = __DIR__ . '/../../../../../fixtures/http_response/bitbucket.yml';
    self::$fixture = file_exists($filePath) && ($content = file_get_contents($filePath)) && is_array($decoded = Yaml::decode($content))
      ? $decoded
      : [];

    return self::$fixture;
  }

  /**
   * Returns a mocked Bitbucket server response for the given request.
   *
   * @param \Psr\Http\Message\RequestInterface $request
   *   The HTTP request.
   * @param array $options
   *   The request options.
   *
   * @return \Psr\Http\Message\ResponseInterface
   *   The mocked API response.
   */
  public function getResponse(RequestInterface $request, array $options): ResponseInterface {
    $root = $this->getRepoRootFromRequestUri($request->getUri());
    $namespace = $this->getNamespaceFromRequestUri($request->getUri());
    $package = $this->getPackageFromRequestUri($request->getUri());

    return new Response(
      200,
      [],
      Json::encode(static::getFixture()[$root][$namespace][$package]),
    );
  }

}
