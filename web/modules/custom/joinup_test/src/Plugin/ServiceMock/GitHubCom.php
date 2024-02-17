<?php

declare(strict_types=1);

namespace Drupal\joinup_test\Plugin\ServiceMock;

use Drupal\Core\Plugin\PluginBase;
use Drupal\http_request_mock\ServiceMockPluginInterface;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Intercepts any HTTP request made to github.com.
 *
 * @ServiceMock(
 *   id = "api_github_com",
 *   label = @Translation("api.github.com"),
 *   weight = 0,
 * )
 */
class GitHubCom extends PluginBase implements ServiceMockPluginInterface {

  /**
   * {@inheritdoc}
   */
  public function applies(RequestInterface $request, array $options): bool {
    return $request->getUri()->getHost() === 'api.github.com';
  }

  /**
   * {@inheritdoc}
   */
  public function getResponse(RequestInterface $request, array $options): ResponseInterface {
    // Parse the requested URI.
    $uri = $request->getUri();
    $path = $uri->getPath();

    $path = str_replace('/repos/', '', $path);
    $path = str_replace('/', '_', $path);

    // Get also the query arguments.
    $query = $uri->getQuery();
    parse_str($query, $query_args);
    $per_page = $query_args['per_page'];
    $filename = $path . '_' . $per_page . '.json';

    $file = __DIR__ . '/../../../fixtures/github_responses/' . $filename;
    if (file_exists($file)) {
      $contents = file_get_contents($file);
      return new Response(200, [], $contents);
    }

    return new Response(404, [], 'Not found');
  }

}
