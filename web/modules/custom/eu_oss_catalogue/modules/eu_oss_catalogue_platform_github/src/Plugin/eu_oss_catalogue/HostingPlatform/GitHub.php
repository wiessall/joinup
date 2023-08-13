<?php

declare(strict_types = 1);

namespace Drupal\eu_oss_catalogue_platform_github\Plugin\eu_oss_catalogue\HostingPlatform;

use Drupal\Cache\Adapter\DrupalAdapter;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Site\Settings;
use Drupal\eu_oss_catalogue\EuOssCatalogueGitHostingPlatformPluginBase;
use Drupal\eu_oss_catalogue\HostingPlatformResultInterface;
use Drupal\eu_oss_catalogue_platform_github\GitHubResult;
use Github\Api\GraphQL;
use Github\AuthMethod;
use Github\Client;
use Psr\Http\Client\ClientInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Git code hosting platform for github.com.
 *
 * @EuOssCatalogueHostingPlatform(
 *   id = "github",
 *   label = @Translation("GitHub"),
 * )
 */
class GitHub extends EuOssCatalogueGitHostingPlatformPluginBase {

  /**
   * Static cache for the GitHub client.
   */
  protected Client $client;

  /**
   * Constructs a new plugin instance.
   *
   * @param array $configuration
   *   The plugin configuration.
   * @param string $pluginId
   *   The plugin ID.
   * @param array $pluginDefinition
   *   The plugin definition.
   * @param \Psr\Http\Client\ClientInterface $httpClient
   *   The HTTP client.
   * @param \Drupal\Core\Site\Settings $settings
   *   The Drupal settings.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   The cache backend.
   */
  public function __construct(
    array $configuration,
    $pluginId,
    $pluginDefinition,
    protected ClientInterface $httpClient,
    protected Settings $settings,
    protected CacheBackendInterface $cache,
  ) {
    parent::__construct($configuration, $pluginId, $pluginDefinition, $httpClient, $settings);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $pluginId, $pluginFefinition): self {
    return new static(
      $configuration,
      $pluginId,
      $pluginFefinition,
      $container->get('http_client'),
      $container->get('settings'),
      $container->get('cache.eu_oss_catalogue'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getRepositories(): iterable {
    foreach ($this->getProjects($this->getConfiguration()['projects']) as $projects) {
      foreach ($projects as $result) {
        yield $result;
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function downloadFile(string $filePath, HostingPlatformResultInterface $result): ?string {
    \assert($result instanceof GitHubResult);
    return $result->metaData['publiccode_yml'];
  }

  /**
   * Returns the GitHub client.
   *
   * @return \Github\Client
   *   The GitHub client.
   */
  protected function getClient(): Client {
    if (!isset($this->client)) {
      $this->client = Client::createWithHttpClient($this->httpClient);
      $token = Settings::get('eu_oss_catalogue', [])['platform_github']['access_token'] ?? NULL;
      if (!$token) {
        // @todo Should we show a message to user or it's only developer stuff?
        throw new \InvalidArgumentException('GitHub access token is not set');
      }
      $this->client->authenticate($token, NULL, AuthMethod::ACCESS_TOKEN);

      // Cache requests to GitHub in 'cache.eu_oss_catalogue' bin.
      $adapter = new DrupalAdapter($this->cache, 'github');
      // Results are cached for 1 day (60 x 60 x 24 = 86400).
      $this->client->addCache($adapter, ['default_ttl' => 86400]);
    }
    return $this->client;
  }

  /**
   * {@inheritdoc}
   */
  protected function getProjects(array $candidates): array {
    if (!isset($this->projects)) {
      $this->projects = [];
      // Build a search query string.
      // @see https://docs.github.com/search-github
      $search = ['is:public', 'archived:false'];
      foreach ($candidates as $candidate) {
        if ($candidate['name']) {
          $search[] = "repo:{$candidate['vendor']}/{$candidate['name']}";
        }
        else {
          $search[] = "owner:{$candidate['vendor']}";
        }
      }
      $search = implode(' ', $search);
      // @todo Should we implement pagination or we limit to 100 and show a
      //   warning message on save (I prefer the 2nd option)?
      $query = <<<GraphQL
        {
          search(type: REPOSITORY, query: "{$search}", first: 100) {
            nodes {
              ... on Repository {
                id
                description
                name
                owner {
                  login
                }
                defaultBranchRef {
                  name
                }
                # How to decouple this? We need a way to make this hookable.
                # Consider using a GraphQL query builder library.
                publiccode_yml: object(expression: "HEAD:publiccode.yml") {
                  ... on Blob {
                    content: text
                  }
                }
              }
            }
          }
        }
        GraphQL;
      $graphQL = new GraphQL($this->getClient());
      foreach ($graphQL->execute($query)['data']['search']['nodes'] as $node) {
        // Repositories without code don't have a default branch.
        if (!empty($node['defaultBranchRef'])) {
          $this->projects[$node['owner']['login']][$node['name']] = new GitHubResult(
            $node['owner']['login'],
            $node['name'],
            $node['defaultBranchRef']['name'],
            $node['id'],
            $node['description'],
            ['publiccode_yml' => $node['publiccode_yml']['content']],
          );
        }
      }
    }
    return $this->projects;
  }

}
