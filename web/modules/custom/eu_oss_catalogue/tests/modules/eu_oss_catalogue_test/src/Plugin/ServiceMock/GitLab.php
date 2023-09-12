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
    $host = $request->getUri()->getHost();
    return in_array($host, [
      'gitlab.com',
      'all.self-hosted.gitlab.example.com',
      'limited.self-hosted.gitlab.example.com',
    ], TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function getResponse(RequestInterface $request, array $options): ResponseInterface {
    $host = $request->getUri()->getHost();
    $query = $request->getBody()->getContents();

    $responseId = match(TRUE) {
      $host === 'all.self-hosted.gitlab.example.com' => 'all.self-hosted.gitlab.example.com',
      str_contains($query, 'group: group(fullPath: \"group1\")') => 'limited.self-hosted.gitlab.example.com group1',
      str_contains($query, 'group: group(fullPath: \"group2\")') =>  'limited.self-hosted.gitlab.example.com group2',
      str_contains($query, 'group: group(fullPath: \"user1\")') => 'limited.self-hosted.gitlab.example.com null',
      str_contains($query, 'project(fullPath: \\"user1\\\\\\/project-1\\"') => 'limited.self-hosted.gitlab.example.com user1/project1',
      str_contains($query, 'group: group(fullPath: \"user2\")') => 'limited.self-hosted.gitlab.example.com null',
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
   *   The HTTp response.
   */
  protected function buildResponse(string $responseId): Response {
    $filePath = __DIR__ . '/../../../../../fixtures/gitlab.yml';
    $body = Json::encode(Yaml::decode(file_get_contents($filePath))[$responseId]);
    return new Response(200, [
      'Content-Type' => 'application/json',
      'Content-Length' => strlen($body),
    ], $body);
  }

}
