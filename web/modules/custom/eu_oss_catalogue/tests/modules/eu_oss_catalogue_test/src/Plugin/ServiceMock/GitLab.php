<?php

declare(strict_types=1);

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
      'wrong.self-hosted.gitlab.example.com',
      'self-hosted.gitlab.example.com/subpath',
      'self-hosted.gitlab.example.com/bit/deeper/subpath',
      'gitlab.opencode.de.example.com',
    ], TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function getResponse(RequestInterface $request, array $options): ResponseInterface {
    // Simplify by removing backslashes and spaces to facilitate comparison.
    $query = preg_replace('/\s/', '', stripcslashes($request->getBody()->getContents()));

    $responseId = match(TRUE) {
      $request->getUri()->getHost() === 'all.self-hosted.gitlab.example.com' => 'all.self-hosted.gitlab.example.com',
      $request->getUri()->getHost() === 'wrong.self-hosted.gitlab.example.com' => 'wrong.self-hosted.gitlab.example.com',
      str_starts_with($query, '{"query":"{group:group(fullPath:"group1")') => 'limited.self-hosted.gitlab.example.com group1',
      str_starts_with($query, '{"query":"{group:group(fullPath:"group2")') => 'limited.self-hosted.gitlab.example.com group2',
      str_starts_with($query, '{"query":"{group:group(fullPath:"user1")') => 'limited.self-hosted.gitlab.example.com null',
      str_starts_with($query, '{"query":"{project(fullPath:"user1\/project-1"') => 'limited.self-hosted.gitlab.example.com user1/project1',
      str_starts_with($query, '{"query":"{group:group(fullPath:"user2")') => 'limited.self-hosted.gitlab.example.com null',
      $query === '' => 'api_detection',
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
    $headers = ['Content-Type' => 'application/json'];
    $body = '';
    if ($responseId === 'api_detection') {
      // GitLab API sets a session cookie.
      // @see https://docs.gitlab.com/ee/api/rest/#session-cookie
      $headers['Set-Cookie'] = ['_gitlab_session=dummy'];
      return new Response(
        200,
        ['Content-Length' => strlen($body)] + $headers,
        $body
      );
    }

    $filePath = __DIR__ . '/../../../../../fixtures/http_response/gitlab.yml';
    $responses = Yaml::decode(file_get_contents($filePath));
    $body = $responses[$responseId];
    if (is_string($body)) {
      $headers['Content-Type'] = preg_match('/^\s*<!DOCTYPE html>/i', $body)
        ? 'text/html'
        : 'text/plain';
    }
    else {
      $body = Json::encode($body);
    }

    return new Response(
      200,
      ['Content-Length' => strlen($body)] + $headers,
      $body
    );
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

}
