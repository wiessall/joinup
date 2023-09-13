<?php

declare(strict_types = 1);

namespace Drupal\eu_oss_catalogue\Plugin\eu_oss_catalogue\HostingPlatform;

use Drupal\Cache\Adapter\DrupalAdapter;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Site\Settings;
use Drupal\eu_oss_catalogue\EuOssCatalogueGitHostingPlatformPluginBase;
use Drupal\eu_oss_catalogue\Exception\MissingAccessTokenException;
use Drupal\eu_oss_catalogue\Model\GitHubSearchType;
use Drupal\eu_oss_catalogue\Model\GitUri;
use Drupal\eu_oss_catalogue\Model\HostingPlatformResult;
use Github\Api\GraphQL;
use Github\AuthMethod;
use Github\Client;
use GraphQL\Actions\Query;
use GraphQL\Entities\Node;
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
   * Base URL of GitHub.
   *
   * @const string
   */
  private const GITHUB_BASE_URL = 'github.com';

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
  public static function create(ContainerInterface $container, array $configuration, $pluginId, $pluginDefinition): self {
    return new static(
      $configuration,
      $pluginId,
      $pluginDefinition,
      $container->get('http_client'),
      $container->get('settings'),
      $container->get('cache.eu_oss_catalogue'),
    );
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
        throw new MissingAccessTokenException('GitHub access token is not set');
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
  public function getProjects(?string $filePath = NULL): iterable {
    // Build a search query string.
    // @see https://docs.github.com/search-github
    $search = ['is:public', 'archived:false'];
    foreach ($this->getConfiguration()['projects'] as $namespace => $repos) {
      if ($repos === NULL) {
        $search[] = "owner:$namespace";
        continue;
      }
      foreach ($repos as $repo) {
        $search[] = "repo:$namespace/$repo";
      }
    }

    // Use a query builder to build a query such as:
    // @code
    // {
    //   search(
    //     type: REPOSITORY
    //     query: "is:public
    //       archived:false
    //       owner:namespace1
    //       repo:namespace2/project2"
    //     first: 100
    //   ) {
    //     nodes {
    //       ... on Repository {
    //         id
    //         description
    //         name
    //         owner {
    //           login
    //         }
    //         defaultBranchRef {
    //           name
    //         }
    //         latestRelease {
    //           tagName
    //           createdAt
    //         }
    //         file: object(expression: "HEAD:publiccode.yml") {
    //           ... on Blob {
    //             text
    //           }
    //         }
    //       }
    //     }
    //   }
    // }
    // @endcode
    $query = new Query('search', [
      'type' => GitHubSearchType::REPOSITORY,
      'query' => implode(' ', $search),
      'first' => 100,
    ]);
    $fragment = $query->nodes([])
      ->on('Repository')
      ->use('id', 'description', 'name')
      ->use((new Node('owner'))->use('login'))
      ->use((new Node('defaultBranchRef'))->use('name'))
      ->use((new Node('latestRelease'))->use('tagName', 'createdAt'));

    if ($filePath) {
      $file = (new Node('object', [
        'expression' => "HEAD:$filePath",
      ]))->alias('file');
      $file->on('Blob')->use('text');
      $fragment->use($file);
    }

    $graphQL = new GraphQL($this->getClient());
    foreach ($graphQL->execute($query->toString())['data']['search']['nodes'] as $node) {
      // Repositories without code don't have a default branch.
      if (!empty($node['defaultBranchRef'])) {
        yield new HostingPlatformResult(
          $node['owner']['login'],
          $node['name'],
          'https://' . self::GITHUB_BASE_URL . "/{$node['owner']['login']}/{$node['name']}",
          $node['defaultBranchRef']['name'],
          $node['latestRelease']['tagName'] ?? NULL,
          $node['latestRelease']['createdAt'] ?? NULL,
          $node['id'],
          $node['description'],
          $node['file']['text'] ?? NULL,
        );
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function isHostingPlatformForUrl(string $repositoryUrl): bool {
    return ($gitUri = GitUri::fromUriString($repositoryUrl))
      && $gitUri->host === self::GITHUB_BASE_URL;
  }

}
