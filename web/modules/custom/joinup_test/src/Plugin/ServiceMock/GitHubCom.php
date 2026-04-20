<?php

declare(strict_types=1);

namespace Drupal\joinup_test\Plugin\ServiceMock;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\State\StateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\http_request_mock\Attribute\ServiceMock;
use Drupal\http_request_mock\ServiceMockPluginInterface;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Intercepts any HTTP request made to github.com.
 */
#[ServiceMock(
  id: "api_github_com",
  label: new TranslatableMarkup("api.github.com"),
  weight: 0,
)]
class GitHubCom extends PluginBase implements ServiceMockPluginInterface, ContainerFactoryPluginInterface {

  /**
   * The States API key of the status of this mock.
   *
   * @const string
   */
  public const string MOCK_STATUS_STATES_KEY = 'joinup_test.github_mock.enabled';

  public function __construct(
    array $configuration,
    string $plugin_id,
    mixed $plugin_definition,
    protected readonly StateInterface $state,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public function applies(RequestInterface $request, array $options): bool {
    return $this->state->get(self::MOCK_STATUS_STATES_KEY, FALSE) && $request->getUri()->getHost() === 'api.github.com';
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
