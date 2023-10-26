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
        default => NULL,
      };
    }
    return $this->response[$key];
  }

}
