<?php

declare(strict_types = 1);

namespace Drupal\eu_oss_catalogue_test\Plugin\ServiceMock;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\Serialization\Yaml;
use Drupal\eu_oss_catalogue_test\IntegrationTestTrait;
use Drupal\http_request_mock\ServiceMockPluginInterface;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;

/**
 * Intercepts any HTTP requests made to GitLab API.
 *
 * @ServiceMock(
 *   id = "gitlab",
 *   label = @Translation("GitLab API"),
 *   weight = 0,
 * )
 */
class GitLab extends PluginBase implements ServiceMockPluginInterface {

  use IntegrationTestTrait;

  /**
   * {@inheritdoc}
   */
  public function applies(RequestInterface $request, array $options): bool {
    if ($this->isIntegrationTest()) {
      return FALSE;
    }
    $apiRoot = $this->getApiRootFromRequestUri($request->getUri());
    return in_array($apiRoot, [
      'all.self-hosted.gitlab.example.com',
      'limited.self-hosted.gitlab.example.com',
      'self-hosted.gitlab.example.com/subpath',
      'self-hosted.gitlab.example.com/bit/deeper/subpath',
    ], TRUE);
  }

  /**
   * Returns the Gitlab API root from the request URI.
   *
   * @return string
   *   The Gitlab API root from the request URI.
   */
  protected function getApiRootFromRequestUri(UriInterface $uri): string {
    $apiRoot = $uri->getHost();
    foreach (explode('/', trim($uri->getPath(), '/')) as $pathItem) {
      if ($pathItem === 'api') {
        return $apiRoot;
      }
      $apiRoot .= "/$pathItem";
    }

    return $apiRoot;
  }

  /**
   * {@inheritdoc}
   */
  public function getResponse(RequestInterface $request, array $options): ResponseInterface {
    $apiRoot = $this->getApiRootFromRequestUri($request->getUri());
    $query = $request->getBody()->getContents();
    $isSelfHostedRequest = str_ends_with($apiRoot, 'self-hosted.gitlab.example.com');
    preg_match('@project\(fullPath:\s+\x5c"([^\x5c].*)\x5c"@', $query, $matches);

    $responseId = match(TRUE) {
      $apiRoot === 'all.self-hosted.gitlab.example.com' => 'all.self-hosted.gitlab.example.com',
      $isSelfHostedRequest && str_contains($query, 'group: group(fullPath: \"group1\")') => 'limited.self-hosted.gitlab.example.com group1',
      $isSelfHostedRequest && str_contains($query, 'group: group(fullPath: \"group2\")') =>  'limited.self-hosted.gitlab.example.com group2',
      $isSelfHostedRequest && str_contains($query, 'group: group(fullPath: \"user1\")') => 'limited.self-hosted.gitlab.example.com null',
      $isSelfHostedRequest && str_contains($query, 'project(fullPath: \\"user1\\\\\\/project-1\\"') => 'limited.self-hosted.gitlab.example.com user1/project1',
      $isSelfHostedRequest && str_contains($query, 'group: group(fullPath: \"user2\")') => 'limited.self-hosted.gitlab.example.com null',
      $apiRoot === 'self-hosted.gitlab.example.com/subpath' => "self-hosted.gitlab.example.com/subpath $matches[1]",
      $apiRoot === 'self-hosted.gitlab.example.com/bit/deeper/subpath' => "self-hosted.gitlab.example.com/bit/deeper/subpath $matches[1]",
      default => 'missing',
    };

    return $this->buildResponse($responseId);
  }

  /**
   * Builds the mocked response.
   *
   * @param string $responseId
   *   The response identifier inside gitlab.yml.
   *
   * @return \GuzzleHttp\Psr7\Response
   *   The HTTP response.
   */
  protected function buildResponse(string $responseId): Response {
    $filePath = __DIR__ . '/../../../../../fixtures/http_response/gitlab.yml';
    $responses = Yaml::decode(file_get_contents($filePath));
    if (!array_key_exists($responseId, $responses)) {
      return new Response(501);
    }

    $body = Json::encode($responses[$responseId]);
    return new Response(200, [
      'Content-Type' => 'application/json',
      'Content-Length' => strlen($body),
    ], $body);
  }

}
