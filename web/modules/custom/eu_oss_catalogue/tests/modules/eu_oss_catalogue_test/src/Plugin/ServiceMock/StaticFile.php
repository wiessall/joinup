<?php

declare(strict_types = 1);

namespace Drupal\eu_oss_catalogue_test\Plugin\ServiceMock;

use Drupal\Core\Plugin\PluginBase;
use Drupal\eu_oss_catalogue_test\IntegrationTestTrait;
use Drupal\http_request_mock\ServiceMockPluginInterface;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Client\RequestExceptionInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Intercepts any HTTP requests made to static files.
 *
 * @ServiceMock(
 *   id = "static_file",
 *   label = @Translation("Static files"),
 *   weight = 0,
 * )
 */
class StaticFile extends PluginBase implements ServiceMockPluginInterface {

  use IntegrationTestTrait;

  /**
   * Static cache for expected responses.
   */
  protected array $response = [];

  /**
   * {@inheritdoc}
   */
  public function applies(RequestInterface $request, array $options): bool {
    return $this->doGetResponse($request) !== NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getResponse(RequestInterface $request, array $options): ResponseInterface {
    return $this->doGetResponse($request);
  }

  /**
   * Returns and caches the response given a request.
   *
   * @param \Psr\Http\Message\RequestInterface $request
   *   The HTTP request.
   *
   * @return \Psr\Http\Message\ResponseInterface|\Psr\Http\Client\RequestExceptionInterface|null
   *   The HTTP response.
   */
  protected function doGetResponse(RequestInterface $request): ResponseInterface|RequestExceptionInterface|null {
    $key = $request->getMethod() . ':' . $request->getUri();
    if (!isset($this->response[$key])) {
      $this->response[$key] = match($key) {
        'HEAD:https://raw.githubusercontent.com/dummy-namespace-1/dummy-name-1/HEAD/images/logo-solution-1.png' => new Response(),
        'HEAD:https://raw.githubusercontent.com/dummy-namespace-2/dummy-name-2/HEAD/path/to/images/logo.jpg' => new Response(404),
        'HEAD:https://all.self-hosted.gitlab.example.com/group1/group1_2/group1_3/project1/raw/HEAD/images/logo.svg' => new Response(),
        'HEAD:https://all.self-hosted.gitlab.example.com/group2/group2_2/group2_3/project2/raw/HEAD/path/to/non-existing/image/logo.gif' => new Response(404),
        'HEAD:https://limited.self-hosted.gitlab.example.com/group1/project-1-1/raw/HEAD/images/logo-of-project-1-1.svg' => new Response(),
        'HEAD:https://limited.self-hosted.gitlab.example.com/group1/project-1-2/raw/HEAD/doesn-t/exist.png' => new Response(404),
        'HEAD:https://raw.githubusercontent.com/dummy-namespace-1/dummy-name-1/HEAD/Solution_1_EN_Screenshot1.jpg',
        'HEAD:https://raw.githubusercontent.com/dummy-namespace-1/dummy-name-1/HEAD/Solution_1_EN_Screenshot2.jpg',
        'HEAD:https://raw.githubusercontent.com/dummy-namespace-1/dummy-name-1/HEAD/Solution_1_DE_Screenshot1.jpg',
        'HEAD:https://raw.githubusercontent.com/dummy-namespace-1/dummy-name-1/HEAD/Solution_1_DE_Screenshot3.jpg',
        'HEAD:https://raw.githubusercontent.com/dummy-namespace-1/dummy-name-1/HEAD/Solution_1_DE_Screenshot4.jpg',
        'HEAD:https://raw.githubusercontent.com/dummy-namespace-1/dummy-name-1/HEAD/Solution_1_IT_Screenshot1.jpg' => new Response(),
        'HEAD:https://raw.githubusercontent.com/dummy-namespace-1/dummy-name-1/HEAD/Solution_1_DE_Screenshot2-missing.jpg' => new Response(402),
        default => NULL,
      };
    }
    return $this->response[$key];
  }

}
