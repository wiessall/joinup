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
    $response = Json::encode(Yaml::decode(file_get_contents(__DIR__ . "/../../../../../fixtures/$host.yml")));
    return new Response(200, [
      'Content-Type' => 'application/json',
      'Content-Length' => strlen($response),
    ], $response);
  }

}
