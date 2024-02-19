<?php

declare(strict_types=1);

namespace Drupal\eu_oss_catalogue_test\Plugin\ServiceMock;

use Drupal\Component\Utility\Random;
use Drupal\Core\Plugin\PluginBase;
use Drupal\http_request_mock\ServiceMockPluginInterface;
use GuzzleHttp\Psr7\Response;
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

  /**
   * Map of static file requests and the expected response status.
   *
   * Assets' which response status is 200 will be served with random images if
   * request method is GET.
   *
   * @const array
   */
  const MOCKED_ASSET_MAP = [
    'https://raw.githubusercontent.com/dummy-namespace-1/dummy-name-1/HEAD/images/logo-solution-1.png' => [
      'HEAD' => 200,
      'GET' => 200,
    ],
    'https://raw.githubusercontent.com/dummy-namespace-2/dummy-name-2/HEAD/path/to/images/logo.jpg' => [
      'HEAD' => 404,
    ],
    'https://all.self-hosted.gitlab.example.com/group1/group1_2/group1_3/project1/raw/HEAD/images/logo.svg' => [
      'HEAD' => 200,
      'GET' => 200,
    ],
    'https://all.self-hosted.gitlab.example.com/group2/group2_2/group2_3/project2/raw/HEAD/path/to/non-existing/image/logo.gif' => [
      'HEAD' => 404,
    ],
    'https://limited.self-hosted.gitlab.example.com/group1/project-1-1/raw/HEAD/images/logo-of-project-1-1.svg' => [
      'HEAD' => 200,
      'GET' => 200,
    ],
    'https://limited.self-hosted.gitlab.example.com/group1/project-1-2/raw/HEAD/doesn-t/exist.png' => [
      'HEAD' => 404,
    ],
    'https://raw.githubusercontent.com/dummy-namespace-1/dummy-name-1/HEAD/Solution_1_EN_Screenshot1.jpg' => [
      'HEAD' => 200,
      'GET' => 200,
    ],
    'https://raw.githubusercontent.com/dummy-namespace-1/dummy-name-1/HEAD/Solution_1_EN_Screenshot2.jpg' => [
      'HEAD' => 200,
      'GET' => 200,
    ],
    'https://raw.githubusercontent.com/dummy-namespace-1/dummy-name-1/HEAD/Solution_1_EN_Screenshot2-updated.jpg' => [
      'HEAD' => 200,
      'GET' => 200,
    ],
    'https://raw.githubusercontent.com/dummy-namespace-1/dummy-name-1/HEAD/Solution_1_DE_Screenshot1.jpg' => [
      'HEAD' => 200,
      'GET' => 200,
    ],
    'https://raw.githubusercontent.com/dummy-namespace-1/dummy-name-1/HEAD/Solution_1_DE_Screenshot3.jpg' => [
      'HEAD' => 200,
      'GET' => 200,
    ],
    'https://raw.githubusercontent.com/dummy-namespace-1/dummy-name-1/HEAD/Solution_1_DE_Screenshot4.jpg' => [
      'HEAD' => 200,
      'GET' => 200,
    ],
    'https://raw.githubusercontent.com/dummy-namespace-1/dummy-name-1/HEAD/Solution_1_IT_Screenshot1.jpg' => [
      'HEAD' => 200,
      'GET' => 200,
    ],
    // Screenshots of Gitlab project-1-1.
    'https://limited.self-hosted.gitlab.example.com/group1/project-1-1/raw/HEAD/Group_1_Project1_IT_Screenshot.jpg' => [
      'HEAD' => 200,
      'GET' => 200,
    ],
    'https://limited.self-hosted.gitlab.example.com/group1/project-1-1/raw/HEAD/assets/de/Group_1_Project1_DE_Screenshot.png' => [
      'HEAD' => 200,
      'GET' => 200,
    ],
    // Logo of Gitlab project-1-2.
    'https://all.self-hosted.gitlab.example.com/uploads/-/system/project/avatar/12/logo.jpg' => [
      'HEAD' => 200,
      'GET' => 200,
    ],
    'https://raw.githubusercontent.com/dummy-namespace-1/dummy-name-1/HEAD/Solution_1_DE_Screenshot2-missing.jpg' => [
      'HEAD' => 402,
    ],
  ];

  /**
   * Static cache for expected responses.
   */
  protected array $response = [];

  /**
   * {@inheritdoc}
   */
  public function applies(RequestInterface $request, array $options): bool {
    return isset(static::MOCKED_ASSET_MAP[(string) $request->getUri()][$request->getMethod()]);
  }

  /**
   * {@inheritdoc}
   */
  public function getResponse(RequestInterface $request, array $options): ResponseInterface {
    return match($request->getMethod()) {
      'HEAD' => $this->getHttpHeadResponse($request),
      'GET' => $this->getHttpGetResponse($request),
      default => new Response(501),
    };
  }

  /**
   * Returns the response of a HEAD request.
   *
   * @param \Psr\Http\Message\RequestInterface $request
   *   The HTTP HEAD request.
   *
   * @return \Psr\Http\Message\ResponseInterface
   *   The HTTP response.
   */
  protected function getHttpHeadResponse(RequestInterface $request): ResponseInterface {
    return new Response(static::MOCKED_ASSET_MAP[(string) $request->getUri()]['HEAD']);
  }

  /**
   * Returns the response of a GET request.
   *
   * @param \Psr\Http\Message\RequestInterface $request
   *   The HTTP HEAD request.
   *
   * @return \Psr\Http\Message\ResponseInterface
   *   The HTTP response.
   */
  protected function getHttpGetResponse(RequestInterface $request): ResponseInterface {
    $statusCode = static::MOCKED_ASSET_MAP[(string) $request->getUri()]['HEAD'];
    $contentType = 'text/plain';
    $responseBody = 'Error';
    if ($statusCode === 200) {
      $filename = basename(parse_url($request->getUri()->getPath(), PHP_URL_PATH));
      $extension = explode('.', $filename)[1];
      $contentType = match($extension) {
        'jpg', 'jpeg', 'png', 'gif', 'webp' => "image/$extension",
        'svg' => "image/svg+xml",
        default => $contentType,
      };
      $responseBody = match($extension) {
        'jpg', 'jpeg', 'png', 'gif', 'webp' => file_get_contents((new Random())->image("temporary://test-$filename", '1x1', '1x1')),
        'svg' => '<svg viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg"><circle cx="50" cy="50" r="50"/></svg>',
        default => 'Some plain text here.',
      };
    }
    return new Response(
      $statusCode,
      ['Content-Type' => $contentType],
      $responseBody
    );
  }

}
