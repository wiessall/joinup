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
 * Mocks requests made to Bitbucket API.
 *
 * @ServiceMock(
 *   id = "eu_oss_catalogue_test_bitbucket",
 *   label = @Translation("API Guesser Bitbucket Mock"),
 *   weight = 10,
 * )
 */
class Bitbucket extends PluginBase implements ContainerFactoryPluginInterface, ServiceMockPluginInterface {

  const BITBUCKET_MOCK_STATE_KEY = 'eu_oss_catalogue_test.bitbucket';

  /**
   * Constructs a Bitbucket service mock instance.
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

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('state')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function applies(RequestInterface $request, array $options): bool {
    return $this->apiRequestMatches($request);
  }

  /**
   * Determines whether this plugin can handle the incoming request or not.
   *
   * @param \Psr\Http\Message\RequestInterface $request
   *   The HTTP request.
   *
   * @return bool
   *   Whether this plugin can handle the incoming request or not.
   */
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
      in_array($package, $this->state->get(static::BITBUCKET_MOCK_STATE_KEY, [])[$root][$vendor] ?? []);
  }

  /**
   * Returns the repository root from the given URI.
   *
   * @param \Psr\Http\Message\UriInterface $uri
   *   The request URI.
   *
   * @return string
   *   The repository root from the given URI.
   */
  protected function getRepoRootFromRequestUri(UriInterface $uri): string {
    $path_parts = explode('/api/2.0/repositories/', $uri->getPath(), 2);
    return $uri->getHost() . $path_parts[0];
  }

  /**
   * Returns the repository namespace from the given URI.
   *
   * @param \Psr\Http\Message\UriInterface $uri
   *   The request URI.
   *
   * @return string
   *   The repository namespace from the given URI.
   */
  protected function getVendorFromRequestUri(UriInterface $uri): ?string {
    $path_parts = explode('/api/2.0/repositories/', $uri->getPath(), 2);
    return explode('/', urldecode($path_parts[1] ?? ''))[0] ?? NULL;
  }

  /**
   * Returns the package name from the given URI.
   *
   * @param \Psr\Http\Message\UriInterface $uri
   *   The request URI.
   *
   * @return string
   *   The package name from the given URI.
   */
  protected function getPackageFromRequestUri(UriInterface $uri): ?string {
    $path_parts = explode('/api/2.0/repositories/', $uri->getPath(), 2);
    return explode('/', urldecode($path_parts[1] ?? ''))[1] ?? NULL;
  }

  /**
   * Returns a mocked Bitbucket server response for the given request.
   *
   * @param \Psr\Http\Message\RequestInterface $request
   *   The HTTP request.
   * @param array $options
   *   The request options.
   *
   * @return \Psr\Http\Message\ResponseInterface
   *   The mocked API response.
   */
  public function getResponse(RequestInterface $request, array $options): ResponseInterface {
    $root = $this->getRepoRootFromRequestUri($request->getUri());
    $vendor = $this->getVendorFromRequestUri($request->getUri());
    $package = $this->getPackageFromRequestUri($request->getUri());

    return new Response(
      200,
      [],
      Json::encode([
        "type" => "repository",
        "full_name" => "$vendor/$package",
        "links" => [
          "self" => [
            "href" => "https://$root/!api/2.0/repositories/$vendor/$package",
          ],
          "html" => [
            "href" => "https://$root/$vendor/$package",
          ],
          "avatar" => [
            "href" => "https://bytebucket.org/ravatar/%7Bdb64fe63-9c7d-4479-82e1-8add140d483a%7D?ts=nodejs",
          ],
          "pullrequests" => [
            "href" => "https://$root/!api/2.0/repositories/$vendor/$package/pullrequests",
          ],
          "commits" => [
            "href" => "https://$root/!api/2.0/repositories/$vendor/$package/commits",
          ],
          "forks" => [
            "href" => "https://$root/!api/2.0/repositories/$vendor/$package/forks",
          ],
          "watchers" => [
            "href" => "https://$root/!api/2.0/repositories/$vendor/$package/watchers",
          ],
          "branches" => [
            "href" => "https://$root/!api/2.0/repositories/$vendor/$package/refs/branches",
          ],
          "tags" => [
            "href" => "https://$root/!api/2.0/repositories/$vendor/$package/refs/tags",
          ],
          "downloads" => [
            "href" => "https://$root/!api/2.0/repositories/$vendor/$package/downloads",
          ],
          "source" => [
            "href" => "https://$root/!api/2.0/repositories/$vendor/$package/src",
          ],
          "clone" => [
            [
              "name" => "https",
              "href" => "https://$root/$vendor/$package.git",
            ],
            [
              "name" => "ssh",
              "href" => "git@$root:$vendor/$package.git",
            ],
          ],
          "issues" => [
            "href" => "https://$root/!api/2.0/repositories/$vendor/$package/issues",
          ],
          "hooks" => [
            "href" => "https://$root/!api/2.0/repositories/$vendor/$package/hooks",
          ],
        ],
        "name" => "$package",
        "slug" => "$package",
        "description" => "CLI tool to browse docker registries using Docker Registry HTTP API V2: https://docs.docker.com/registry/spec/api/",
        "scm" => "git",
        "website" => "",
        "owner" => [
          "display_name" => "Atlassian Labs",
          "links" => [
            "self" => [
              "href" => "https://$root/!api/2.0/workspaces/%7B6f461d1e-a3dd-433b-a0e3-7a69daf6ea47%7D",
            ],
            "avatar" => [
              "href" => "https://$root/account/$vendor/avatar/",
            ],
            "html" => [
              "href" => "https://$root/%7B6f461d1e-a3dd-433b-a0e3-7a69daf6ea47%7D/",
            ],
          ],
          "type" => "team",
          "uuid" => "{6f461d1e-a3dd-433b-a0e3-7a69daf6ea47}",
          "username" => $vendor,
        ],
        "workspace" => [
          "type" => "workspace",
          "uuid" => "{6f461d1e-a3dd-433b-a0e3-7a69daf6ea47}",
          "name" => "Atlassian Labs",
          "slug" => $vendor,
          "links" => [
            "avatar" => [
              "href" => "https://$root/workspaces/$vendor/avatar/?ts=1674615417",
            ],
            "html" => [
              "href" => "https://$root/$vendor/",
            ],
            "self" => [
              "href" => "https://$root/!api/2.0/workspaces/$vendor",
            ],
          ],
        ],
        "is_private" => FALSE,
        "project" => [
          "type" => "project",
          "key" => "ATL",
          "uuid" => "{a5db4f36-0092-467f-a592-75354e2d38d2}",
          "name" => "Atlassian Labs",
          "links" => [
            "self" => [
              "href" => "https://$root/!api/2.0/workspaces/$vendor/projects/ATL",
            ],
            "html" => [
              "href" => "https://$root/$vendor/workspace/projects/ATL",
            ],
            "avatar" => [
              "href" => "https://$root/account/user/$vendor/projects/ATL/avatar/32?ts=1448850256",
            ],
          ],
        ],
        "fork_policy" => "allow_forks",
        "created_on" => "2017-06-27T10:43:55.541794+00:00",
        "updated_on" => "2018-01-09T11:37:47.680057+00:00",
        "size" => 1142848,
        "language" => "nodejs",
        "has_issues" => TRUE,
        "has_wiki" => FALSE,
        "uuid" => "{db64fe63-9c7d-4479-82e1-8add140d483a}",
        "mainbranch" => [
          "name" => "main",
          "type" => "branch",
        ],
        "override_settings" => [
          "default_merge_strategy" => FALSE,
          "branching_model" => FALSE,
        ],
      ]),
    );
  }

  /**
   * Returns a non-implemented response.
   *
   * @return \Psr\Http\Message\ResponseInterface
   *   A non-implemented response.
   */
  protected static function notImplementedResponse(): ResponseInterface {
    return new Response(501);
  }

}
