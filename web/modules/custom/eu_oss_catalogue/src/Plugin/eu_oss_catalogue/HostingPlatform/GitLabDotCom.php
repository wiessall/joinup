<?php

declare(strict_types = 1);

namespace Drupal\eu_oss_catalogue\Plugin\eu_oss_catalogue\HostingPlatform;

use Drupal\Core\Utility\Error;
use Drupal\eu_oss_catalogue\EuOssCatalogueGitHostingPlatformPluginBase;
use Drupal\eu_oss_catalogue\Model\GitLabReleaseSort;
use Drupal\eu_oss_catalogue\Model\GitUri;
use Drupal\eu_oss_catalogue\Model\Project;
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
 * )
 */
class GitLabDotCom extends EuOssCatalogueGitHostingPlatformPluginBase {

  /**
   * Base URL of Gitlab.
   *
   * @const string
   */
  final protected const GITLAB_BASE_URL = 'gitlab.com';

  /**
   * Static cache for GitLab client.
   */
  protected Client $client;

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
   *    A list of file paths keyed by a file identifier.
   *
   * @return iterable
   *   Array \Drupal\eu_oss_catalogue\Model\Project instances.
   */
  protected function doGetProjects(array $requestedFiles = []): iterable {
    foreach ($this->getConfiguration()['projects'] as $namespace => $repos) {
      $limitToRepos = is_array($repos);
      $endCursor = NULL;
      do {
        $query = $this->buildGroupQuery($namespace, $requestedFiles, $endCursor);
        try {
          $response = $this->getClient()->graphql()->execute($query->toString());
        }
        catch (\Throwable $exception) {
          // @todo Retry with REST API as a fallback.
          Error::logException($this->logger, $exception, "Can't get the group '@namespace' from @url", [
            '@namespace' => $namespace,
            '@url' => $this->getBaseUrl(),
          ]);
          break;
        }

        if (empty($response['data']['group']['projects']['nodes'])) {
          // This namespace has no projects, exit do {...} while (...).
          break;
        }

        $hasNextPage = $response['data']['group']['projects']['pageInfo']['hasNextPage'];
        $endCursor = $response['data']['group']['projects']['pageInfo']['endCursor'];
        foreach ($response['data']['group']['projects']['nodes'] as $project) {
          // Ignore when...
          if (
            // There's no default branch, thus no code, or...
            empty($project['repository']['rootRef']) ||
            // The project is archived, or...
            !empty($project['archived']) ||
            // A set of projects were requested but this project is not in list.
            ($limitToRepos && !in_array($project['path'], $repos, TRUE))
          ) {
            // Keep track of what's left.
            if ($limitToRepos) {
              unset($repos[array_search($project['path'], $repos)]);
            }
            continue;
          }
          yield $this->createHostingPlatformResult($namespace, $project, $requestedFiles);
        }
      } while ($hasNextPage);

      // Not all projects were resolved. This happens when the namespace is a
      // user. We parse user projects only if they were explicitly designated,
      // i.e. both namespace and project name were specified. Otherwise, GitLab
      // requires authentication and not all self-hosted platforms allow new
      // user registration.
      if ($limitToRepos) {
        foreach ($repos as $name) {
          $query = $this->buildProjectQuery($namespace, $name, $requestedFiles);
          try {
            $project = $this->getClient()->graphql()->execute($query->toString())['data']['project'] ?? NULL;
            if ($project && !empty($project['repository']['rootRef']) && empty($project['archived'])) {
              yield $this->createHostingPlatformResult($namespace, $project, $requestedFiles);
            }
            return;
          }
          catch (\Throwable $exception) {
            // Log only a warning as we retry later by using REST API.
            Error::logException($this->logger, $exception, "Can't get the project '@project' from @url using GraphQL API", [
              '@project' => "$namespace/$name",
              '@url' => $this->getBaseUrl(),
            ], LogLevel::WARNING);
          }

          // It wasn't possible to get the project info with GraphQL API. This
          // could happen on old versions of GitLab installed. Give it another
          // chance by falling back to REST API.
          if ($project = $this->getProjectWithRestApi("$namespace/$name")) {
            yield $this->createHostingPlatformResult($namespace, $project);
          }
        }
      }
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
   *     A list of file paths keyed by a file identifier.
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
      ->use('path', 'id', 'description', 'archived')
      ->releases(['sort' => GitLabReleaseSort::CREATED_DESC, 'first' => 1])
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
   *
   * @code
   * {
   *   project(fullPath: "namespace/name") {
   *     path
   *     id
   *     description
   *     archived
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
   *    A list of file paths keyed by a file identifier.
   *
   * @return \GraphQL\Actions\Query
   *   A GraphQL query builder instance.
   */
  protected function buildProjectQuery(string $namespace, string $name, array $requestedFiles = []): Query {
    $query = new Query('project', ['fullPath' => "$namespace/$name"]);
    $fragment = $query->use('path', 'id', 'description', 'archived')
      ->releases(['sort' => GitLabReleaseSort::CREATED_DESC, 'first' => 1])
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
   * Returns the project info, if any, by using REST API.
   *
   * @param string $fullPath
   *   The project's full name/path.
   * @return array|null
   *   The project array having a structure similar to the one retrieved by
   *   GraphQL response or NULL if there's no project with this name.
   */
  protected function getProjectWithRestApi(string $fullPath): ?array {
    try {
      $response = $this->getClient()->projects()->show($fullPath);
      $project = [
        'path' => $response['path'],
        'repository' => ['rootRef' => $response['default_branch']],
        'id' => (string) $response['id'],
        'description' => $response['description'],
      ];
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
      Error::logException($this->logger, $exception, "Can't get the project '@project' from @url using REST API", [
        '@project' => $fullPath,
        '@url' => $this->getBaseUrl(),
      ]);
      return NULL;
    }

    try {
      $pager = new ResultPager($this->getClient(), 1);
      $pager->fetchAll($this->getClient()->repositories(), 'releases', [$fullPath]);
      $response = $this->getClient()->repositories()->releases($response['id']);
      $project['releases']['nodes'][0]['tagName'] = $response[0]['tag_name'];
      $project['releases']['nodes'][0]['createdAt'] = $response[0]['created_at'];
    }
    catch (\RuntimeException $exception) {
      if ($exception->getCode() === 404) {
        // Return silently.
        return $project;
      }
    }
    catch (\Throwable $exception) {
      // Return silently, but log a warning.
      Error::logException($this->logger, $exception, "Can't get the releases for project '@project' from @url using REST API", [
        '@project' => $fullPath,
        '@url' => $this->getBaseUrl(),
      ], LogLevel::WARNING);
    }

    return $project;
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
  protected function createHostingPlatformResult(string $namespace, array $project, array $requestedFiles = []): Project {
    // Get files by reverse engineering from file path to file IDs.
    $files = [];

    if ($requestedFiles) {
      $flippedFiles = array_flip($requestedFiles);
      foreach ($project['repository']['blobs']['nodes'] as $fileData) {
        $files[$flippedFiles[$fileData['path']]] = $fileData['rawBlob'];
      }
    }

    return new Project(
      $namespace,
      $project['path'],
      "{$this->getBaseUrl()}/$namespace/{$project['path']}",
      $project['repository']['rootRef'],
      $project['releases']['nodes'][0]['tagName'] ?? NULL,
      $project['releases']['nodes'][0]['createdAt'] ?? NULL,
      $project['id'],
      $project['description'],
      $files,
    );
  }

  /**
   * {@inheritdoc}
   */
  public function isHostingPlatformForUrl(string $repositoryUrl): bool {
    return ($gitUri = GitUri::fromUriString($repositoryUrl))
      && $gitUri->host === self::GITLAB_BASE_URL;
  }

  /**
   * Returns the GitLab instance base URL.
   *
   * @return string
   *   The GitLab instance base URL.
   */
  protected function getBaseUrl(): string {
    return $this->getConfiguration()['url'] ?? 'https://gitlab.com';
  }

}
