<?php

declare(strict_types=1);

namespace Drupal\eu_oss_catalogue\Plugin\eu_oss_catalogue\HostingPlatform;

use Bitbucket\Client;
use Bitbucket\HttpClient\Builder;
use Bitbucket\ResultPager;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Site\Settings;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\eu_oss_catalogue\EuOssCatalogueGitHostingPlatformPluginBase;
use Drupal\eu_oss_catalogue\Model\GitUri;
use Drupal\eu_oss_catalogue\Model\Project;
use Psr\Http\Client\ClientInterface;

/**
 * Git code hosting platform for repositories hosted on bitbucket.org.
 *
 * @EuOssCatalogueHostingPlatform(
 *   id = "bitbucket_org",
 *   label = @Translation("Bitbucket (bitbucket.org)"),
 *   baseUrl = "https://bitbucket.org",
 *   weight = -5,
 * )
 */
class Bitbucket extends EuOssCatalogueGitHostingPlatformPluginBase {

  /**
   * The Bitbucket API client.
   *
   * @var \Bitbucket\Client
   */
  protected Client $bitbucketClient;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    ClientInterface $httpClient,
    Settings $settings,
    LoggerChannelInterface $logger,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $httpClient, $settings, $logger);
    // Make Bitbucket client use Drupal's HTTP client.
    $clientBuilder = new Builder($this->httpClient);
    $this->bitbucketClient = new Client($clientBuilder);
  }

  /**
   * {@inheritdoc}
   */
  public function doGetProjects(array $requestedFiles = []): iterable {
    foreach ($this->getConfiguration()['projects'] as $namespace => $repositories) {
      try {
        foreach ($this->getProjectsFromNamespace($namespace, $repositories) as $node) {
          $mainBranch = $node['mainbranch']['name'] ?? NULL;
          $packageName = $node['slug'];
          if (!$mainBranch) {
            $this->logger->info(
              "No main branch was found in '%repository' on '%base_url'.",
              [
                '%repository' => "$namespace/$packageName",
                '%base_url' => $this->getConfiguration()['baseUrl'],
              ]
            );
            continue;
          }

          // If the logo URL's "ts" query is "default" or is equal to the
          // detected language, it is a generated logo, so we don't add it to
          // the project.
          $logoUrl = $node['links']['avatar']['href'];
          $logoUrlParsed = UrlHelper::parse($node['links']['avatar']['href']);
          if (
            !isset($logoUrlParsed['query']['ts']) ||
            in_array($logoUrlParsed['query']['ts'], ['default', $node['language']])
          ) {
            $logoUrl = NULL;
          }

          [
            'tag' => $tag,
            'date' => $tagDate,
          ] = $this->getLatestTagAndTagDate($namespace, $packageName);

          yield new Project(
            namespace: $node['workspace']['slug'],
            name: $packageName,
            url: $node['links']['html']['href'],
            platform: $this->getPluginId(),
            defaultBranch: $mainBranch,
            latestReleaseTag: $tag,
            latestReleaseDate: $tagDate,
            platformReference: substr($node['uuid'], 1, 36),
            description: $node['description'],
            logo: $logoUrl,
            numberOfForks: $this->getForks($namespace, $packageName),
            numberOfPullRequests: $this->getNumberOfPullRequests($namespace, $packageName),
            files: $this->getFilesContent($namespace, $packageName, $mainBranch, $requestedFiles),
          );
        }
      }
      catch (\Exception) {
        $this->logger->debug(
          "Cannot fetch projects of namespace '%namespace' on '%base_url'.",
          [
            '%namespace' => $namespace,
            '%base_url' => $this->getConfiguration()['baseUrl'],
          ]
        );
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function isFileRawUrl(string $url, string $projectUrl): bool {
    // Both bytebucket and bitbucket are valid, and urls must contain a
    // branch, tag, or commit hash.
    $namespaceName = preg_replace(
      '#^' . preg_quote($this->getConfiguration()['baseUrl'], '#') . '#',
      '',
      $projectUrl
    );
    $namespaceNameQuoted = preg_quote(trim($namespaceName, '/'), '#');

    // Repository file.
    $isRepositoryFile = preg_match(
      "#^https?://(?>bitbucket\.org/!?api|bytebucket\.org/!?api|api\.bitbucket\.org)/2\.0/repositories/$namespaceNameQuoted/src/.*/.+$#i",
      $url
    );

    if ($isRepositoryFile) {
      return TRUE;
    }

    // Project avatar (bytebucket domain).
    return (bool) preg_match(
        "#^https://bytebucket\.org/ravatar/%7B[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}%7D\?ts=.+$#i",
        $url
      );
  }

  /**
   * {@inheritdoc}
   */
  public function doGetFileRawUrl(string $url, string $projectUrl): ?string {
    $filePath = preg_replace(
      '#^' . preg_quote($projectUrl) . '/#',
      '',
      $url
    );

    $gitUri = GitUri::fromUriString($projectUrl);
    try {
      $repo = $this->bitbucketClient->repositories()
        ->workspaces($gitUri->namespace)
        ->show($gitUri->name);
      if (empty($mainBranch = $repo['mainbranch']['name'] ?? NULL)) {
        return NULL;
      }

      $file = $this->bitbucketClient->repositories()
        ->workspaces($gitUri->namespace)
        ->src($gitUri->name)
        ->show($mainBranch, $filePath);
      return $file['links']['self']['href'] ?? NULL;
    }
    catch (\Exception $e) {
      $this->logger->notice(
        "Cannot find the '%filename' file in '%repository' on '%base_url' in branch / tag / commit '%reference'.",
        [
          '%filename' => $url,
          '%repository' => "$gitUri->namespace/$gitUri->name",
          '%base_url' => $this->getConfiguration()['baseUrl'],
          '%reference' => $mainBranch ?? 'undetermined',
        ]
      );
      return NULL;
    }
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
  public function getLabel(): TranslatableMarkup {
    return $this->pluginDefinition['label'];
  }

  /**
   * {@inheritdoc}
   */
  public function isHostingPlatformForUrl(string $repositoryUrl): bool {
    return str_starts_with($repositoryUrl, 'https://bitbucket.org/');
  }

  /**
   * {@inheritdoc}
   */
  public function getBaseFromRepoUrl(string $repositoryUrl): ?string {
    return $this->getConfiguration()['baseUrl'];
  }

  /**
   * Returns the name of the latest tag, if any.
   *
   * @param string $namespace
   *   The namespace of the project.
   * @param string $name
   *   The name of the project.
   *
   * @return array|null
   *   An array with the latest tag at the "tag" key, and tag date at the "date"
   *   key. Both values can be NULL. Returns NULL if there are no tags on the
   *   repository.
   */
  protected function getLatestTagAndTagDate(string $namespace, string $name): ?array {
    try {
      $tags = $this->bitbucketClient->repositories()
        ->workspaces($namespace)
        ->refs($name)
        ->tags()
        ->list(['pagelen' => 1, 'sort' => '-target.date']);
      return empty($tags['values'][0])
        ? NULL
        : [
          'tag' => $tags['values'][0]['name'] ?? NULL,
          'date' => $tags['values'][0]['date'] ?? $tags['values'][0]['target']['date'] ?? NULL,
        ];
    }
    catch (\Exception) {
      $this->logger->debug(
        "Cannot find any tags in '%repository' on '%base_url'.",
        [
          '%repository' => "$namespace/$name",
          '%base_url' => $this->getConfiguration()['baseUrl'],
        ]
      );
    }
    return NULL;
  }

  /**
   * Returns the content of the given files of a project.
   *
   * @param string $namespace
   *   The namespace of the project.
   * @param string $name
   *   The name of the project.
   * @param string $commitTagBranch
   *   A tag, branch or commit hash to look for the files in.
   * @param array $filenames
   *   The name of the files to look for.
   *
   * @return array
   *   The content of each file as array. Keys are the same as in the
   *   $filenames argument. If a file is missing then its key is omitted.
   */
  protected function getFilesContent(string $namespace, string $name, string $commitTagBranch, array $filenames): array {
    $files = [];
    foreach ($filenames as $delta => $filename) {
      try {
        $files[$delta] = $this->bitbucketClient->repositories()
          ->workspaces($namespace)
          ->src($name)
          ->download($commitTagBranch, $filename)
          ->getContents();
      }
      catch (\Exception) {
        $this->logger->debug(
          "Cannot find the '%filename' file in '%repository' on '%base_url' in branch / tag / commit '%reference'.",
          [
            '%filename' => $filename,
            '%repository' => "$namespace/$name",
            '%base_url' => $this->getConfiguration()['baseUrl'],
            '%reference' => $commitTagBranch,
          ]
        );
      }
    }
    return $files;
  }

  /**
   * Returns the API response with the projects in the given namespace.
   *
   * @param string $namespace
   *   The namespace.
   * @param null|string[] $names
   *   The project names to look for in the given namespace.
   *
   * @return iterable
   *   The projects in the given namespace.
   */
  protected function getProjectsFromNamespace(string $namespace, ?array $names = NULL): iterable {
    $searchQuery = $names
      ? '(slug="' . implode('" OR slug="', $names) . '")'
      : NULL;
    try {
      $paginator = new ResultPager($this->bitbucketClient);
      $projectsClient = $this->bitbucketClient->repositories()
        ->workspaces($namespace);

      return $paginator->fetchAllLazy(
        $projectsClient,
        'list',
        [array_filter(['q' => $searchQuery])]
      );
    }
    catch (\Exception) {
      $this->logger->debug(
        "Cannot fetch projects of namespace '%namespace' on '%base_url'.",
        [
          '%namespace' => $namespace,
          '%base_url' => $this->getConfiguration()['baseUrl'],
        ]
      );
    }
    return [];
  }

  /**
   * Returns the number of forks.
   *
   * @param string $namespace
   *   The namespace.
   * @param string $name
   *   The project name.
   *
   * @return int|null
   *   Number of how many forks the repository has.
   */
  protected function getForks(string $namespace, string $name): ?int {
    try {
      $forks = $this->bitbucketClient->repositories()
        ->workspaces($namespace)
        ->forks($name)
        ->list(['pagelen' => 1]);
      return $forks['size'] ?? NULL;
    }
    catch (\Exception) {
      $this->logger->debug(
        "Cannot fetch number of forks of '%repository' on '%base_url'.",
        [
          '%repository' => "$namespace/$name",
          '%base_url' => $this->getConfiguration()['baseUrl'],
        ]
      );
    }
    return NULL;
  }

  /**
   * Returns the number of open pull requests, if any.
   *
   * @param string $namespace
   *   The namespace.
   * @param string $name
   *   The project name.
   *
   * @return int|null
   *   Number of how many open pull requests the repository has.
   */
  protected function getNumberOfPullRequests(string $namespace, string $name): ?int {
    try {
      $prs = $this->bitbucketClient->repositories()
        ->workspaces($namespace)
        ->pullRequests($name)
        ->list(['state' => 'OPEN', 'pagelen' => 1]);
      return $prs['size'] ?? NULL;
    }
    catch (\Exception) {
      $this->logger->debug(
        "Cannot fetch number of PRs of '%repository' on '%base_url'.",
        [
          '%repository' => "$namespace/$name",
          '%base_url' => $this->getConfiguration()['baseUrl'],
        ]
      );
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguration(): array {
    return $this->configuration + $this->defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration): void {
    $this->configuration = $configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'baseUrl' => $this->getPluginDefinition()['baseUrl'] ?? NULL,
    ];
  }

}
