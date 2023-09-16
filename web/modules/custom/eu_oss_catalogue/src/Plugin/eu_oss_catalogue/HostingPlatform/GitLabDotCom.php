<?php

declare(strict_types = 1);

namespace Drupal\eu_oss_catalogue\Plugin\eu_oss_catalogue\HostingPlatform;

use Drupal\eu_oss_catalogue\EuOssCatalogueGitHostingPlatformPluginBase;
use Drupal\eu_oss_catalogue\Model\GitLabReleaseSort;
use Drupal\eu_oss_catalogue\Model\GitUri;
use Drupal\eu_oss_catalogue\Model\Project;
use Gitlab\Client;
use GraphQL\Actions\Query;

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
        $response = $this->getClient()->graphql()->execute($query->toString());

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
          $project = $this->getClient()->graphql()->execute($query->toString())['data']['project'] ?? NULL;
          if ($project && !empty($project['repository']['rootRef']) && empty($project['archived'])) {
            yield $this->createHostingPlatformResult($namespace, $project, $requestedFiles);
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
   * Creates a code hosting platform result instance.
   *
   * @param string $namespace
   *   The project's namespace.
   * @param array $project
   *   The project array, from the GraphQL results.
   *
   * @return \Drupal\eu_oss_catalogue\Model\Project
   *   A code hosting platform result instance.
   */
  protected function createHostingPlatformResult(string $namespace, array $project, array $requestedFiles): Project {
    $host = $this->getConfiguration()['url'] ?? ('https://' . self::GITLAB_BASE_URL);

    // Get files by reverse engineering from file path to file IDs.
    $files = [];
    $flippedFiles = array_flip($requestedFiles);
    foreach ($project['repository']['blobs']['nodes'] as $fileData) {
      $files[$flippedFiles[$fileData['path']]] = $fileData['rawBlob'];
    }

    return new Project(
      $namespace,
      $project['path'],
      "$host/$namespace/{$project['path']}",
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

}
