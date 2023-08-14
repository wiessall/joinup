<?php

declare(strict_types = 1);

namespace Drupal\eu_oss_catalogue_test\Plugin\ServiceMock;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\State\StateInterface;
use Drupal\http_request_mock\ServiceMockPluginInterface;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Mocks requests made to Gitlab API.
 *
 * @ServiceMock(
 *   id = "eu_oss_catalogue_test_gitlab",
 *   label = @Translation("API Guesser Gitlab Mock"),
 *   weight = 10,
 * )
 */
class GitlabServiceMock extends PluginBase implements ContainerFactoryPluginInterface, ServiceMockPluginInterface {

  const GITLAB_MOCK_STATE_KEY = 'eu_oss_catalogue_test.gitlab';

  /**
   * Constructs a \Drupal\Component\Plugin\PluginBase object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected StateInterface $state
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('state')
    );
  }

  public function applies(RequestInterface $request, array $options): bool {
    return $this->apiRequestMatches($request);
  }

  protected function apiRequestMatches(RequestInterface $request): bool {
    $request_uri = $request->getUri();
    if ($request->getMethod() !== 'GET' || $request_uri->getScheme() !== 'https') {
      return FALSE;
    }

    $root = $this->getRepoRootFromRequestUri($request_uri);
    $vendor = $this->getVendorFromRequestUri($request_uri);
    $package = $this->getPackageFromRequestUri($request_uri);

    return !empty($vendor) &&
      !empty($package) &&
      in_array($package, $this->state->get(static::GITLAB_MOCK_STATE_KEY, [])[$root][$vendor] ?? []);
  }

  protected function getRepoRootFromRequestUri(UriInterface $uri): string {
    $path_parts = explode('/api/v4/projects/', $uri->getPath(), 2);
    return $uri->getHost() . $path_parts[0];
  }

  protected function getVendorFromRequestUri(UriInterface $uri): ?string {
    $path_parts = explode('/api/v4/projects/', $uri->getPath(), 2);
    return explode('/', urldecode($path_parts[1] ?? ''))[0] ?? NULL;
  }

  protected function getPackageFromRequestUri(UriInterface $uri): ?string {
    $path_parts = explode('/api/v4/projects/', $uri->getPath(), 2);
    return explode('/', urldecode($path_parts[1] ?? ''))[1] ?? NULL;
  }

  public function getResponse(RequestInterface $request, array $options): ResponseInterface {
    $root = $this->getRepoRootFromRequestUri($request->getUri());
    $vendor = $this->getVendorFromRequestUri($request->getUri());
    $package = $this->getPackageFromRequestUri($request->getUri());

    return new Response(
      200,
      [],
      Json::encode([
        "id" => 1234,
        "description" => "Project description",
        "name" => $package,
        "name_with_namespace" => "$vendor / $package",
        "path" => $package,
        "path_with_namespace" => "$vendor/$package",
        "created_at" => "2021-04-06T14:05:40.208Z",
        "default_branch" => "main",
        "tag_list" => [],
        "topics" => [],
        "ssh_url_to_repo" => "git@$root:$vendor/$package.git",
        "http_url_to_repo" => "https://$root/$vendor/$package.git",
        "web_url" => "https://$root/$vendor/$package",
        "readme_url" => "https://$root/$vendor/$package/-/blob/main/README.md",
        "forks_count" => 6,
        "avatar_url" => null,
        "star_count" => 1,
        "last_activity_at" => "2023-08-11T09:15:21.333Z",
        "namespace" => [
          "id" => 211,
          "name" => $vendor,
          "path" => $vendor,
          "kind" => "group",
          "full_path" => $vendor,
          "parent_id" => null,
          "avatar_url" => "/uploads/-/system/group/avatar/211/index.png",
          "web_url" => "https://$root/groups/$vendor"
        ]
      ]),
    );
  }

  protected static function notImplementedResponse(): ResponseInterface {
    return new Response(501);
  }

}
