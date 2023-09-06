<?php

declare(strict_types = 1);

namespace Drupal\eu_oss_catalogue_test\Plugin\ServiceMock;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Plugin\PluginBase;
use Drupal\eu_oss_catalogue_test\IntegrationTestTrait;
use Drupal\http_request_mock\ServiceMockPluginInterface;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Intercepts any HTTP requests made to GitHub API.
 *
 * @ServiceMock(
 *   id = "git_hub",
 *   label = @Translation("GitHub API"),
 *   weight = 0,
 * )
 */
class GitHub extends PluginBase implements ServiceMockPluginInterface {

  use IntegrationTestTrait;

  /**
   * {@inheritdoc}
   */
  public function applies(RequestInterface $request, array $options): bool {
    return !$this->isIntegrationTest() && ($request->getUri()->getHost() === 'api.github.com');
  }

  /**
   * {@inheritdoc}
   */
  public function getResponse(RequestInterface $request, array $options): ResponseInterface {
    $response = Json::encode(
      [
        'data' => [
          'search' => [
            'nodes' => [
              0 => [
                'id' => 'OYWYKQvqtguoqeWoQ9j7SwXj6Fekpt0t',
                'description' => NULL,
                'name' => 'dummy-name',
                'owner' => [
                  'login' => 'dummy-namespace',
                ],
                'defaultBranchRef' => [
                  'name' => 'develop',
                ],
              ],
            ],
          ],
        ],
      ]
    );
    return new Response(200, [
      'Content-Type' => 'application/json',
      'Content-Length' => strlen($response),
    ], $response);
  }

}
