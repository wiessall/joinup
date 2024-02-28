<?php

declare(strict_types=1);

namespace Drupal\eu_oss_catalogue\Plugin\eu_oss_catalogue\HostingPlatform;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Utility\Error;
use Drupal\eu_oss_catalogue\EuOssCatalogueGitHostingPlatformPluginBase;
use Drupal\eu_oss_catalogue\Model\GitLab\ReleaseSort;
use Drupal\eu_oss_catalogue\Model\GitUri;
use Drupal\eu_oss_catalogue\Model\Project;
use Gitlab\Api\Issues;
use Gitlab\Client;
use Gitlab\ResultPager;
use GraphQL\Actions\Query;
use Psr\Log\LogLevel;

/**
 * Git code hosting platform for gitlab.com repositories.
 *
 * @EuOssCatalogueHostingPlatform(
 *   id = "gitlab_com",
 *   label = @Translation("GitLab (gitlab.com)"),
 *   baseUrl = "https://gitlab.com",
 *   weight = -10,
 * )
 */
class GitLabDotCom extends EuOssCatalogueGitHostingPlatformPluginBase {

  /**
   * Static cache for GitLab client.
   */
  protected Client $client;

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
    // As this method is reused also for self-hosted GitLab, we provide generic
    // examples covering also https://gitlab.com.
    if (preg_match("@^$projectUrl/(?:-/)?blob/([^/]+)/(.+)$@", $url, $parts)) {
      // Example:
      // https://git.drupalcode.org/project/drupal/-/blob/11.x/core/misc/logo/drupal-logo.svg
      // becomes
      // https://git.drupalcode.org/project/drupal/-/raw/11.x/core/misc/logo/drupal-logo.svg
      return "$projectUrl/raw/$parts[1]/$parts[2]";
    }

    // Repository relative URL (e.g. https://gitlab.com/owner/project/dir/file),
    // even this URL doesn't exist. The repository relative path has been
    // previously prefixed with the project URL, but we ignore this. The
    // reference (branch, commit, tag) is unknown, so we assume HEAD.
    if (preg_match("@^$projectUrl((/[^/]+)+)$@", $url, $parts)) {
      // Example:
      // https://git.drupalcode.org/project/drupal/core/misc/logo/drupal-logo.svg
      // becomes
      // https://git.drupalcode.org/project/drupal/-/raw/HEAD/core/misc/logo/drupal-logo.svg
      return "$projectUrl/raw/HEAD$parts[1]";
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  protected function isFileRawUrl(string $url, string $projectUrl): bool {
    if (!str_starts_with($url, $projectUrl)) {
      return FALSE;
    }

    // We reuse this method also for self-hosted GitLab for which we provide
    // the following examples, covering also https://gitlab.com:
    // Valid raw URLs:
    // - https://git.drupalcode.org/project/drupal/-/raw/11.x/core/misc/logo/drupal-logo.svg
    // - https://git.drupalcode.org/project/drupal/raw/11.x/core/misc/logo/drupal-logo.svg
    // Invalid URLs:
    // - https://git.drupalcode.org/project/drupal/-/11.x/core/misc/logo/drupal-logo.svg
    return (bool) preg_match("@^$projectUrl/(-/)?raw/(.+)$@", $url);
  }

  /**
   * Returns the GitLab client.
   *
   * @return \Gitlab\Client
   *   The GitLab client.
   */
  protected function getClient(): Client {
    if (!isset($this->client)) {
      $this->client = Client::createWithHttpClient($this->httpClient);
    }
    return $this->client;
  }

  /**
   * Returns the list of projects.
   *
   * @param string[] $requestedFiles
   *   A list of file paths keyed by a file identifier.
   *
   * @return iterable
   *   Array \Drupal\eu_oss_catalogue\Model\Project instances.
   */
  protected function doGetProjects(array $requestedFiles = []): iterable {
    foreach ($this->getConfiguration()['projects'] as $namespace => $repos) {
      $endCursor = NULL;
      do {
        $query = $this->buildGroupQuery($namespace, $requestedFiles, $endCursor);
        try {
          $response = $this->getClient()->graphql()->execute($query->toString());
          // Stop in case we have some errors or when the response is not an
          // array.
          if (!is_array($response)) {
            throw new \RuntimeException('Response returned by GraphQL client is not an array.');
          }
          if (isset($response['errors'][0]['message'])) {
            throw new \RuntimeException($response['errors'][0]['message']);
          }
        }
        catch (\RuntimeException $exception) {
          if ($exception->getCode() === 404) {
            // Group doesn't exist. We set the response in this way to be picked
            // up a few lines later and retry as user instead of a group.
            $response['data']['group'] = NULL;
          }
          elseif ($exception->getCode() === 500) {
            // Couldn't get the group projects with GraphQL API. This could
            // happen on old versions of GitLab installed. Give it another
            // chance by falling back to REST API.
            $response['data']['group'] = $this->getGroupProjectsWithRestApi($namespace, $requestedFiles);
          }
          else {
            Error::logException($this->logger, $exception, "Can't get the group {namespace} from {url}. Message: {message}", [
              'namespace' => $namespace,
              'url' => $this->getConfiguration()['baseUrl'],
              'message' => $exception->getMessage(),
            ]);
            // Done with this namespace.
            break;
          }
        }

        if ($response['data']['group'] === NULL) {
          // Not a group. It might be a user namespace, but let's see.
          // For users, we only deal with the case where projects were
          // explicitly passed, i.e. both namespace and project name
          // were specified. Otherwise, GitLab requires authentication
          // and we don't support that.
          if ($repos !== NULL) {
            foreach ($this->fetchIndividualProjects($namespace, $repos, $requestedFiles) as $project) {
              yield $project;
            }
          }
          // Done with this namespace.
          break;
        }

        foreach ($this->filterGroupProjects($response, $repos) as $project) {
          yield $this->createProject($namespace, $project, $requestedFiles);
        }

        // Honour pagination.
        $hasNextPage = $response['data']['group']['projects']['pageInfo']['hasNextPage'];
        $endCursor = $response['data']['group']['projects']['pageInfo']['endCursor'];
      } while ($hasNextPage);
    }
  }

  /**
   * Builds the group query.
   *
   * This renders a query similar to:
   * @code
   * {
   *   group: group(fullPath: "group1/group2") {
   *     projects {
   *       pageInfo {
   *         hasNextPage
   *         endCursor
   *       }
   *       nodes {
   *         path
   *         id
   *         description
   *         archived
   *         forksCount
   *         starCount
   *         openIssuesCount
   *         avatarUrl
   *         openMergeRequestsCount
   *         releases(sort: CREATED_DESC first: 1) {
   *           nodes {
   *             tagName
   *             createdAt
   *           }
   *         }
   *         repository {
   *           rootRef
   *           blobs(paths: ["publiccode.yml"]) {
   *             nodes {
   *               rawBlob
   *             }
   *           }
   *         }
   *       }
   *     }
   *   }
   * }
   * @endcode
   *
   * @param string $namespace
   *   The namespace.
   * @param string[] $requestedFiles
   *   A list of file paths keyed by a file identifier.
   * @param string|null $endCursor
   *   The end cursor, if any.
   *
   * @return \GraphQL\Actions\Query
   *   A GraphQL query builder instance.
   */
  protected function buildGroupQuery(string $namespace, array $requestedFiles = [], ?string $endCursor = NULL): Query {
    $query = new Query('group', ['fullPath' => $namespace]);
    $query->alias('group');

    $filters = $endCursor ? ['after' => $endCursor] : [];

    $fragment = $query->projects($filters)
      ->pageInfo([])
      ->use('hasNextPage', 'endCursor')
      ->prev()
      ->nodes([])
      ->use(
        'path',
        'id',
        'description',
        'archived',
        'forksCount',
        'starCount',
        'openIssuesCount',
        'avatarUrl',
        'openMergeRequestsCount'
      )
      ->releases(['sort' => ReleaseSort::CREATED_DESC, 'first' => 1])
      ->nodes([])
      ->use('tagName', 'createdAt')
      ->prev()
      ->prev()
      ->repository([])
      ->use('rootRef');

    if ($requestedFiles) {
      $fragment->blobs(['paths' => array_values($requestedFiles)])
        ->nodes([])
        ->use('path', 'rawBlob');
    }

    return $query;
  }

  /**
   * Builds a specific project query.
   *
   * This renders a query similar to:
   * @code
   * {
   *   project(fullPath: "namespace/name") {
   *     path
   *     id
   *     description
   *     archived
   *     forksCount
   *     starCount
   *     openIssuesCount
   *     avatarUrl
   *     openMergeRequestsCount
   *     releases(sort: CREATED_DESC first: 1) {
   *       nodes {
   *         tagName
   *         createdAt
   *       }
   *     }
   *     repository {
   *       rootRef
   *       blobs(paths: ["README.md", "file.txt"]) {
   *         nodes {
   *           path
   *           rawBlob
   *         }
   *       }
   *     }
   *   }
   * }
   * @endcode
   *
   * @param string $namespace
   *   The namespace.
   * @param string $name
   *   The project name.
   * @param string[] $requestedFiles
   *   A list of file paths keyed by a file identifier.
   *
   * @return \GraphQL\Actions\Query
   *   A GraphQL query builder instance.
   */
  protected function buildProjectQuery(string $namespace, string $name, array $requestedFiles = []): Query {
    $query = new Query('project', ['fullPath' => "$namespace/$name"]);
    $fragment = $query
      ->use(
        'path',
        'id',
        'description',
        'archived',
        'forksCount',
        'starCount',
        'openIssuesCount',
        'avatarUrl',
        'openMergeRequestsCount'
      )
      ->releases(['sort' => ReleaseSort::CREATED_DESC, 'first' => 1])
      ->nodes([])
      ->use('tagName', 'createdAt')
      ->prev()
      ->prev()
      ->repository([])
      ->use('rootRef');

    if ($requestedFiles) {
      $fragment->blobs(['paths' => array_values($requestedFiles)])
        ->nodes([])
        ->use('path', 'rawBlob');
    }

    return $query;
  }

  /**
   * Creates a code hosting platform result instance.
   *
   * @param string $namespace
   *   The project's namespace.
   * @param array $project
   *   The project array, from the GraphQL results.
   * @param string[] $requestedFiles
   *   (optional) A list of file blobs keyed by their file identifier.
   *
   * @return \Drupal\eu_oss_catalogue\Model\Project
   *   A code hosting platform result instance.
   */
  protected function createProject(string $namespace, array $project, array $requestedFiles = []): Project {
    // Get files by reverse engineering from file path to file IDs.
    $files = [];

    if ($requestedFiles) {
      $flippedFiles = array_flip($requestedFiles);
      foreach ($project['repository']['blobs']['nodes'] as $fileData) {
        // @see self:getProjectWithRestApi()
        $fileId = $fileData['__file_id'] ?? $flippedFiles[$fileData['path']];
        $files[$fileId] = $fileData['rawBlob'];
      }
    }

    return new Project(
      namespace: $namespace,
      name: $project['path'],
      url: "{$this->getConfiguration()['baseUrl']}/$namespace/{$project['path']}",
      platform: $this->getPluginId(),
      defaultBranch: $project['repository']['rootRef'],
      latestReleaseTag: $project['releases']['nodes'][0]['tagName'] ?? NULL,
      latestReleaseDate: $project['releases']['nodes'][0]['createdAt'] ?? NULL,
      platformReference: $project['id'],
      description: $project['description'],
      logo: $project['avatarUrl'] ?? NULL,
      numberOfForks: $project['forksCount'],
      numberOfStars: $project['starCount'],
      numberOfIssues: $project['openIssuesCount'],
      numberOfPullRequests: $project['openMergeRequestsCount'] ?? NULL,
      files: $files,
    );
  }

  /**
   * Narrows the list of group projects to those we're interested in.
   *
   * @param array $response
   *   The response from the query prepared in ::buildGroupQuery().
   * @param array|null $repos
   *   A list of requested projects within the namespace to narrow on.
   *
   * @return array[]
   *   A list of filtered projects.
   */
  protected function filterGroupProjects(array $response, ?array $repos): array {
    $limitTo = is_array($repos) ? array_flip($repos) : NULL;
    return array_filter(
      $response['data']['group']['projects']['nodes'],
      function (array $project) use ($limitTo): bool {
        // No list has been requested OR the project is in the requested list.
        return ($limitTo === NULL || isset($limitTo[$project['path']])) &&
          // There is a default branch.
          !empty($project['repository']['rootRef']);
      });
  }

  /**
   * Returns the results from projects where the group query failed.
   *
   * A group query might fail for several reasons:
   * - It wasn't a group, but user namespace.
   * - The GitLab code hosting platform version is too old and lack some GraphQL
   *   featured.
   *
   * Fetch user projects only if they were explicitly designated, i.e. both
   * namespace and project name were specified. Otherwise, GitLab requires
   * authentication and we don't support that.
   *
   * @param string $namespace
   *   The namespace we assume it's a user namespace.
   * @param array $names
   *   A list of project names within in the given namespace.
   * @param string[] $requestedFiles
   *   (optional) A list of file blobs keyed by their file identifier.   *.
   *
   * @return iterable
   *   Generates a list of user projects.
   */
  protected function fetchIndividualProjects(string $namespace, array $names, array $requestedFiles = []): iterable {
    foreach ($names as $name) {
      $query = $this->buildProjectQuery($namespace, $name, $requestedFiles);
      try {
        $project = $this->getClient()->graphql()->execute($query->toString())['data']['project'] ?? NULL;
        if ($project && !empty($project['repository']['rootRef'])) {
          yield $this->createProject($namespace, $project, $requestedFiles);
        }
        continue;
      }
      catch (\Throwable $exception) {
        // Log only a warning as we retry later by using REST API.
        Error::logException($this->logger, $exception, "Can't get the project {project} from {url} using GraphQL API", [
          'project' => "$namespace/$name",
          'url' => $this->getConfiguration()['baseUrl'],
        ], LogLevel::WARNING);
      }

      // It wasn't possible to get the project info with GraphQL API. This could
      // happen on old versions of GitLab installed. Give it another chance by
      // falling back to REST API.
      if ($project = $this->getProjectWithRestApi("$namespace/$name", $requestedFiles)) {
        yield $this->createProject($namespace, $project, $requestedFiles);
      }
    }
  }

  /**
   * Returns the project info, if any, by using REST API.
   *
   * This is a fallback method to get project data for GitLab code hosting
   * platforms that doesn't support full GraphQL, most probably because of an
   * older GitLab version.
   *
   * @param string $fullPath
   *   The project's full name/path.
   * @param string[] $requestedFiles
   *   (optional) A list of file blobs keyed by their file identifier.
   *
   * @return array|null
   *   The project array having a structure similar to the one retrieved by
   *   GraphQL response or NULL if there's no project with this name.
   */
  protected function getProjectWithRestApi(string $fullPath, array $requestedFiles = []): ?array {
    try {
      $response = $this->getClient()->projects()->show($fullPath);
      $project = $this->buildProjectArray($response);
    }
    catch (\RuntimeException $exception) {
      if ($exception->getCode() === 404) {
        // There's no such a project. Return silently.
        return NULL;
      }
      throw $exception;
    }
    catch (\Throwable $exception) {
      // Fail silently but log if the project data cannot be retrieved.
      Error::logException($this->logger, $exception, "Can't get the project {project} from {url} using REST API", [
        'project' => $fullPath,
        'url' => $this->getConfiguration()['baseUrl'],
      ]);
      return NULL;
    }

    $this->addProjectLastReleaseWithRestApi($project, $fullPath);
    $this->addProjectFilesWithRestApi($project, $fullPath, $requestedFiles);
    $this->addProjectOpenIssuesRestApi($project, $project['id']);

    return $project;
  }

  /**
   * Builds a project array similar to the one produced by GraphQL response.
   *
   * @param array $response
   *   The GitLab REST API response.
   *
   * @return array
   *   A project array.
   */
  protected function buildProjectArray(array $response): array {
    return [
      'path' => $response['path'],
      'repository' => [
        'rootRef' => $response['default_branch'],
        'blobs' => ['nodes' => []],
      ],
      'id' => (string) $response['id'],
      'description' => $response['description'],
      'archived' => $response['archived'] ?? FALSE,
      'forksCount' => $response['forks_count'],
      'starCount' => $response['star_count'],
      'openIssuesCount' => $response['open_issues'] ?? NULL,
      'avatarUrl' => $response['avatar_url'],
    ];
  }

  /**
   * Appends latest release info to the project array.
   *
   * @param array $project
   *   The project array.
   * @param string $fullPath
   *   The project full path.
   */
  protected function addProjectLastReleaseWithRestApi(array &$project, string $fullPath): void {
    // Try to get the latest release.
    try {
      $response = $this->getClient()->repositories()->releases($fullPath);
      if ($response) {
        $project['releases']['nodes'][0]['tagName'] = $response[0]['tag_name'];
        $project['releases']['nodes'][0]['createdAt'] = $response[0]['created_at'];
      }
    }
    catch (\RuntimeException $exception) {
      if ($exception->getCode() !== 404) {
        throw $exception;
      }
      // No releases, which is legit as well.
    }
    catch (\Throwable $exception) {
      // Log a warning.
      Error::logException($this->logger, $exception, "Can't get the releases for project {project} from {url} using REST API", [
        'project' => $fullPath,
        'url' => $this->getConfiguration()['baseUrl'],
      ], LogLevel::WARNING);
    }
  }

  /**
   * Appends the file blobs to the project array.
   *
   * @param array $project
   *   The project array.
   * @param string $fullPath
   *   The project full path.
   * @param string[] $requestedFiles
   *   (optional) A list of file blobs keyed by their file identifier.
   */
  protected function addProjectFilesWithRestApi(array &$project, string $fullPath, array $requestedFiles = []): void {
    // Try to get raw files.
    foreach ($requestedFiles as $fileId => $requestedFile) {
      try {
        $response = $this->getClient()->repositoryFiles()->getRawFile($fullPath, $requestedFile, $project['repository']['rootRef']);
        $project['repository']['blobs']['nodes'][] = [
          'rawBlob' => $response,
          '__file_id' => $fileId,
        ];
      }
      catch (\Throwable $exception) {
        // Log a warning.
        Error::logException($this->logger, $exception, "Can't get the file {file} for project {project} from {url} using REST API", [
          'project' => $fullPath,
          'url' => $this->getConfiguration()['baseUrl'],
          'file' => $requestedFile,
        ], LogLevel::WARNING);
      }
    }
  }

  /**
   * Appends open issues info to the project array.
   *
   * @param array $project
   *   The project array.
   * @param string $projectId
   *   The project id.
   */
  protected function addProjectOpenIssuesRestApi(array &$project, string $projectId): void {
    try {
      $pager = new ResultPager($this->getClient());
      $issues = $pager->fetchAll(
        $this->getClient()->issues(),
        'all',
        [$projectId, ['state' => Issues::STATE_OPENED]]
      );
      $project['releases']['nodes'][0]['open_issues'] = count($issues);
    }
    catch (\Throwable $exception) {
      // Log a warning.
      Error::logException($this->logger, $exception, "Can't get open issues for project {project_id} from {url} using REST API", [
        'project_id' => $projectId,
        'url' => $this->getConfiguration()['baseUrl'],
      ], LogLevel::WARNING);
    }
  }

  /**
   * Returns the group projects by using GitLab REST API.
   *
   * @param string $namespace
   *   The group namespace.
   * @param string[] $requestedFiles
   *   (optional) A list of file blobs keyed by their file identifier.
   *
   * @return array[]|null
   *   A list of projects structured in the same way as if has been returned by
   *   the similar GraphQL query.
   */
  protected function getGroupProjectsWithRestApi(string $namespace, array $requestedFiles = []): ?array {
    try {
      $projects = [];
      $pager = new ResultPager($this->getClient());
      foreach ($pager->fetchAll($this->getClient()->groups(), 'projects', [$namespace]) as $row) {
        $project = $this->buildProjectArray($row);
        $this->addProjectLastReleaseWithRestApi($project, "$namespace/{$project['path']}");
        $this->addProjectFilesWithRestApi($project, "$namespace/{$project['path']}", $requestedFiles);
        $this->addProjectOpenIssuesRestApi($project, $project['id']);
        $projects[] = $project;
      }
      return ['projects' => ['nodes' => $projects]];
    }
    catch (\RuntimeException $exception) {
      if ($exception->getCode() !== 404) {
        throw $exception;
      }
      // This group doesn't exist.
      return NULL;
    }
  }

}
