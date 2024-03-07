<?php

declare(strict_types=1);

namespace Drupal\eu_oss_catalogue\Plugin\eu_oss_catalogue\HostingPlatform;

use Drupal\Cache\Adapter\DrupalAdapter;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Site\Settings;
use Drupal\Core\Utility\Error;
use Drupal\eu_oss_catalogue\EuOssCatalogueGitHostingPlatformPluginBase;
use Drupal\eu_oss_catalogue\Exception\MissingAccessTokenException;
use Drupal\eu_oss_catalogue\Model\GitHub\SearchType;
use Drupal\eu_oss_catalogue\Model\GitHub\State;
use Drupal\eu_oss_catalogue\Model\GitUri;
use Drupal\eu_oss_catalogue\Model\Project;
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
 *   baseUrl = "https://github.com",
 *   weight = -10,
 * )
 */
class GitHub extends EuOssCatalogueGitHostingPlatformPluginBase {

  protected const PULLREQUEST_FILTER = ['states' => State::OPEN];

  /**
   * Default terms for GitHub search.
   *
   * @const string[]
   */
  protected const DEFAULT_SEARCH_TERMS = ['is:public', 'fork:true'];

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
   * @param \Drupal\Core\Logger\LoggerChannelInterface $logger
   *   The EU OSS Catalogue logger.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   The cache backend.
   */
  public function __construct(
    array $configuration,
    $pluginId,
    $pluginDefinition,
    protected ClientInterface $httpClient,
    protected Settings $settings,
    protected LoggerChannelInterface $logger,
    protected CacheBackendInterface $cache,
  ) {
    parent::__construct($configuration, $pluginId, $pluginDefinition, $httpClient, $settings, $logger);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('http_client'),
      $container->get('settings'),
      $container->get('logger.channel.eu_oss_catalogue'),
      $container->get('cache.eu_oss_catalogue'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildConfigurationForm($form, $form_state);
    $form['baseUrl']['#type'] = 'value';
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function isHostingPlatformForUrl(string $repositoryUrl): bool {
    $gitUri = GitUri::fromUriString($repositoryUrl);
    return $gitUri && $gitUri->getSchemeAndHost() === $this->getBaseFromRepoUrl($repositoryUrl);
  }

  /**
   * {@inheritdoc}
   */
  public function getBaseFromRepoUrl(string $repositoryUrl): ?string {
    return $this->getConfiguration()['baseUrl'];
  }

  /**
   * {@inheritdoc}
   */
  protected function doGetFileRawUrl(string $url, string $projectUrl): ?string {
    $projectPath = substr($projectUrl, strlen($this->getConfiguration()['baseUrl']) + 1);
    if (preg_match("@^$projectUrl/blob((/[^/]+){3,}.*)$@", $url, $parts)) {
      // Example:
      // https://github.com/drupal/core/blob/11.x/misc/logo/drupal-logo.svg
      // becomes
      // https://raw.githubusercontent.com/drupal/core/11.x/misc/logo/drupal-logo.svg
      return "https://raw.githubusercontent.com/$projectPath$parts[1]";
    }

    // Repository relative URL (e.g. https://github.com/owner/project/dir/file),
    // even this URL doesn't exist. The repository relative path has been
    // previously prefixed with the project URL, but we ignore this. The
    // reference (branch, commit, tag) is unknown, so we assume HEAD.
    if (preg_match("@^$projectUrl/(.+)$@", $url, $parts)) {
      // Example:
      // https://github.com/drupal/core/misc/logo/drupal-logo.svg becomes
      // https://raw.githubusercontent.com/drupal/core/HEAD/misc/logo/drupal-logo.svg
      return "https://raw.githubusercontent.com/$projectPath/HEAD/$parts[1]";
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  protected function isFileRawUrl(string $url, string $projectUrl): bool {
    if (!str_starts_with($url, 'https://raw.githubusercontent.com/')) {
      return FALSE;
    }

    // The URL path should start with at least three parts.
    // Valid: https://raw.githubusercontent.com/drupal/core/HEAD/misc/logo/drupal-logo.svg
    // Invalid: https://raw.githubusercontent.com/drupal/core/drupal-logo.svg
    return (bool) preg_match('@^(/[^/]+){3}/.+$@', parse_url($url, PHP_URL_PATH));
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
  public function doGetProjects(array $requestedFiles = []): iterable {
    // Build a search query as an array.
    // @see https://docs.github.com/search-github
    $search = [];
    foreach ($this->getConfiguration()['projects'] as $namespace => $repos) {
      if ($repos === NULL) {
        $search[] = "owner:$namespace";
        continue;
      }
      foreach ($repos as $repo) {
        $search[] = "repo:$namespace/$repo";
      }
    }

    $graphQL = new GraphQL($this->getClient());

    while ($search) {
      // The search query can be a very big list of search terms, thus we split
      // in chunks, to avoid overloading the GitHub API request.
      $searchQuery = $this->getSearchQuery($search);
      $endCursor = NULL;
      do {
        $query = $this->buildProjectsQuery($searchQuery, $requestedFiles, $endCursor);
        try {
          $response = $graphQL->execute($query->toString());
        }
        catch (\Throwable $exception) {
          Error::logException($this->logger, $exception, "Can't get GitHub projects. Query: @query, Message: @message", [
            '@query' => $query->toString(),
            '@message' => $exception->getMessage(),
          ]);
          break;
        }

        foreach ($response['data']['search']['nodes'] as $node) {
          if (empty($node['defaultBranchRef'])) {
            // Repositories without a default branch don't have code yet.
            continue;
          }

          $files = [];
          foreach ($requestedFiles as $fileId => $filePath) {
            if ($content = ($node["file__$fileId"]['text'] ?? NULL)) {
              $files[$fileId] = $content;
            }
          }

          $logo = $node['openGraphImageUrl'];
          if ($logo  && str_starts_with($logo, 'https://opengraph.githubassets.com/')) {
            // If a repository lacks a logo uploaded by the user, GitHub
            // generates one under https://opengraph.githubassets.com. Keep only
            // logos uploaded by the user.
            $logo = NULL;
          }

          yield new Project(
            namespace: $node['owner']['login'],
            name: $node['name'],
            url: "{$this->getConfiguration()['baseUrl']}/{$node['owner']['login']}/{$node['name']}",
            platform: $this->getPluginId(),
            defaultBranch: $node['defaultBranchRef']['name'],
            latestReleaseTag: $node['latestRelease']['tagName'] ?? NULL,
            latestReleaseDate: $node['latestRelease']['createdAt'] ?? NULL,
            platformReference: $node['id'],
            description: $node['description'],
            logo: $logo,
            numberOfForks: $node['forkCount'],
            numberOfStars: $node['stargazerCount'],
            numberOfIssues: $node['issues']['totalCount'],
            numberOfPullRequests: $node['pullRequests']['totalCount'],
            files: $files,
          );
        }

        // The GraphQL query is paginated to 100 results.
        $hasNextPage = $response['data']['search']['pageInfo']['hasNextPage'];
        $endCursor = $response['data']['search']['pageInfo']['endCursor'];
      } while ($hasNextPage);
    }
  }

  /**
   * Builds the projects query.
   *
   * This renders a query similar to:
   *
   * @code
   * {
   *   search(
   *     type: REPOSITORY
   *     query: "is:public fork:true owner:user1 repo:org2/project2"
   *     first: 20
   *   ) {
   *     pageInfo {
   *       hasNextPage
   *       endCursor
   *     }
   *     nodes {
   *       ... on Repository {
   *         id
   *         description
   *         name
   *         forkCount
   *         stargazerCount
   *         openGraphImageUrl
   *         owner {
   *           login
   *         }
   *         defaultBranchRef {
   *           name
   *         }
   *         latestRelease {
   *           tagName
   *           createdAt
   *         }
   *         issues (states: OPEN) {
   *           totalCount
   *         }
   *         file: object(expression: "HEAD:publiccode.yml") {
   *           ... on Blob {
   *             text
   *           }
   *         }
   *       }
   *     }
   *   }
   * }
   * @endcode
   *
   * @param string $searchQuery
   *   The search query. See https://docs.github.com/search-github.
   * @param string[] $requestedFiles
   *   A list of file paths keyed by a file identifier.
   * @param string|null $endCursor
   *   The end cursor, if any.
   *
   * @return \GraphQL\Actions\Query
   *   A GraphQL query builder instance.
   */
  protected function buildProjectsQuery(string $searchQuery, array $requestedFiles = [], ?string $endCursor = NULL): Query {
    $filters = [
      'type' => SearchType::REPOSITORY,
      'query' => $searchQuery,
      'first' => 20,
    ] + ($endCursor ? ['after' => $endCursor] : []);

    $query = new Query('search', $filters);
    $query->pageInfo([])->use('hasNextPage', 'endCursor');

    $fragment = $query->nodes([])
      ->on('Repository')
      ->use('id', 'description', 'name', 'forkCount', 'stargazerCount', 'openGraphImageUrl')
      ->use((new Node('owner'))->use('login'))
      ->use((new Node('defaultBranchRef'))->use('name'))
      ->use((new Node('latestRelease'))->use('tagName', 'createdAt'))
      ->use((new Node('issues', ['states' => State::OPEN]))->use('totalCount'))
      ->use((new Node('pullRequests', static::PULLREQUEST_FILTER))->use('totalCount'));

    foreach ($requestedFiles as $fileId => $filePath) {
      $file = (new Node('object', ['expression' => "HEAD:$filePath"]))
        ->alias("file__$fileId");
      $file->on('Blob')->use('text');
      $fragment->use($file);
    }

    return $query;
  }

  /**
   * Returns the search query to be used by GraphQL.
   *
   * @param array $search
   *   A list of search query terms passed by reference. As this method is
   *   called, the list narrows by removing the already processed search terms.
   *
   * @return string
   *   The search query to be passed to GraphQL query.
   */
  protected function getSearchQuery(array &$search): string {
    // Be kind to GitHub. Limit to 100 namespaces or projects.
    $searchChunk = array_splice($search, 0, 100);
    return implode(' ', [...static::DEFAULT_SEARCH_TERMS, ...$searchChunk]);
  }

}
