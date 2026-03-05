<?php

declare(strict_types=1);

namespace Drupal\eu_oss_catalogue_test\Plugin\ServiceMock;

use Drupal\Component\Serialization\Json;
use Drupal\Component\Serialization\Yaml;
use Drupal\Component\Utility\Random;
use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\http_request_mock\Attribute\ServiceMock;
use Drupal\http_request_mock\ServiceMockPluginInterface;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;

/**
 * Mocks requests made to Bitbucket API.
 */
#[ServiceMock(
  id: "eu_oss_catalogue_test_bitbucket",
  label: new TranslatableMarkup("API Guesser Bitbucket Mock"),
  weight: 10,
)]
class Bitbucket extends PluginBase implements ServiceMockPluginInterface {

  const JSON_RESPONSE_HEADERS = ['content-type' => 'application/json'];
  const TEXT_RESPONSE_HEADERS = ['content-type' => 'text/plain; charset=utf-8'];

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
    if (
      !in_array($request->getMethod(), ['GET', 'HEAD']) ||
      $request->getUri()->getScheme() !== 'https'
    ) {
      return FALSE;
    }

    return !is_null($this->getMockResponse($request));
  }

  /**
   * Returns the path parts from the given URI.
   *
   * @return array
   *   The path parts from the given URI.
   */
  protected function getPathParts(UriInterface $uri): array {
    $pathParts = explode('/api/2.0/repositories/', $uri->getPath(), 2);
    if (count($pathParts) < 2) {
      // Can happen that the request was made to api.bitbucket.org/api/2.0/.
      $pathParts = explode('/2.0/repositories/', $uri->getPath(), 2);
    }
    return $pathParts;
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
    $pathParts = $this->getPathParts($uri);
    return $uri->getHost() . $pathParts[0];
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
    $mockResponse = $this->getMockResponse($request);
    $status = $mockResponse['_http_status'] ?? 200;
    if (is_array($mockResponse)) {
      unset($mockResponse['_http_status']);
    }
    $headers = self::JSON_RESPONSE_HEADERS;
    $requestIsHead = $request->getMethod() === 'HEAD';

    // Assume that URLs ending with .png, .gif or .jpg/.jpeg expected to get a
    // binary file response.
    $filename = basename((string) $request->getUri());
    $parts = explode('.', $filename);
    $extension = count($parts) > 1
      ? end($parts)
      : NULL;
    if ($extension) {
      $headers = match($extension) {
        'jpg', 'jpeg', 'png', 'gif', => ['content-type' => "image/$extension"],
        'svg' => ['content-type' => 'image/svg+xml'],
        'yml' => self::TEXT_RESPONSE_HEADERS,
        default => $headers,
      };
      if (!$requestIsHead) {
        $mockResponse = match($extension) {
          'jpg', 'jpeg', 'png', 'gif' => file_get_contents((new Random())->image("temporary://test-$filename", '1x1', '1x1')),
          default => $mockResponse,
        };
      }
    }

    $responseBody = is_array($mockResponse)
      ? Json::encode($mockResponse)
      : $mockResponse;

    return new Response(
      $status,
      $headers,
      $requestIsHead ? NULL : $responseBody,
    );
  }

  /**
   * Returns the mocked response, if any can be found.
   *
   * @return array|string|null
   *   The mocked response. Array responses are returned as JSON string, string
   *   responses are returned either as plain text, or as binary content,
   *   depending on whether the request URL ends in a file name with "png",
   *   "gif", "jpg/jpeg" extension. Returns NULL if no response mock can be
   *   found.
   */
  protected function getMockResponse(RequestInterface $request): array|string|null {
    $requestUri = $request->getUri();
    $root = $this->getRepoRootFromRequestUri($requestUri);
    $path = $pathWithQuery = $requestUri->getPath();
    if ($query = $requestUri->getQuery()) {
      $pathWithQuery .= '?' . $query;
    }

    if (!array_key_exists($root, self::getFixture())) {
      return NULL;
    }

    if (array_key_exists($pathWithQuery, self::getFixture()[$root])) {
      return self::getFixture()[$root][$pathWithQuery];
    }

    if (array_key_exists($path, self::getFixture()[$root])) {
      return self::getFixture()[$root][$path];
    }

    // If this is a simple repository info request, then try to get it from the
    // general namespace response mock if it is available.
    if (preg_match('#^(?>/api)?/2\.0/repositories/(?>[^/]+)/([^/]+)$#', $path, $matches)) {
      $name = $matches[1];
      $pathWithoutNamespace = $pathWithoutNamespaceWithQuery = preg_replace('#/' . preg_quote($name) . '#', '', $path);
      if ($query) {
        $pathWithoutNamespaceWithQuery .= '?' . $requestUri->getQuery();
      }

      $projects = self::getFixture()[$root][$pathWithoutNamespaceWithQuery]
        ?? self::getFixture()[$root][$pathWithoutNamespace]
        ?? [];

      foreach ($projects['values'] ?? [] as $project) {
        if ($project['slug'] === $name) {
          return $project;
        }
      }
    }

    return NULL;
  }

}
