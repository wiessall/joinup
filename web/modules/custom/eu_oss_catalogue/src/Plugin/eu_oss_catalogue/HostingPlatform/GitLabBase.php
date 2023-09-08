<?php

declare(strict_types = 1);

namespace Drupal\eu_oss_catalogue\Plugin\eu_oss_catalogue\HostingPlatform;

use Drupal\eu_oss_catalogue\EuOssCatalogueGitHostingPlatformPluginBase;
use Drupal\eu_oss_catalogue\Model\GitLabReleaseSort;
use Drupal\eu_oss_catalogue\Model\HostingPlatformResult;
use Gitlab\Client;
use GraphQL\Actions\Query;

/**
 * Base class for GitLab plugins.
 */
abstract class GitLabBase extends EuOssCatalogueGitHostingPlatformPluginBase {

  /**
   * Static cache for GitLab client.
   */
  protected Client $client;

  /**
   * {@inheritdoc}
   */
  public function getProjects(?string $filePath = NULL): iterable {
    foreach ($this->doGetProjects($filePath) as $project) {
      yield $project;
    }
  }

  /**
   * Returns the GitLab client.
   *
   * @return \Gitlab\Client
   *   The GitLab client.
   */
  protected function getClient(): Client {
    if (!isset($this->client)) {
      $this->client = new Client();
    }
    return $this->client;
  }

  /**
   * Returns the projects.
   *
   * @param string|null $filePath
   *   The file path, if one has been specified.
   *
   * @return iterable
   *   Array \Drupal\eu_oss_catalogue\Model\HostingPlatformResult instances.
   */
  protected function doGetProjects(?string $filePath = NULL): iterable {
    $projects = [];
    foreach ($this->getConfiguration()['projects'] as [
      'namespace' => $namespace,
      'name' => $name,
    ]) {
      $projects[$namespace] ??= [];
      if ($name) {
        $projects[$namespace][$name] = $name;
      }
    }

    foreach ($projects as $namespace => $repos) {
      $endCursor = NULL;
      do {
        $query = $this->buildGroupQuery($namespace, $filePath, $endCursor);
        $response = $this->getClient()->graphql()->execute($query->toString());

        if (empty($response['data']['group']['projects']['nodes'])) {
          // This namespace has no projects, exit do {...} while (...).
          break;
        }

        $hasNextPage = $response['data']['group']['projects']['pageInfo']['hasNextPage'];
        $endCursor = $response['data']['group']['projects']['pageInfo']['endCursor'];
        foreach ($response['data']['group']['projects']['nodes'] as $project) {
          // No projects.
          if (empty($project['repository']['rootRef'])) {
            // Keep track of what's left.
            unset($repos[$project['name']]);
            continue;
          }

          // Limit to list if provided.
          if (!$repos || isset($repos[$project['path']])) {
            // Keep track of what's left.
            unset($repos[$project['path']]);
            yield $this->createHostingPlatformResult($namespace, $project);
          }
        }
      } while ($hasNextPage);

      // Not all projects were resolved. This happens when the namespace is a
      // user. We parse user projects only if they were explicitly designated,
      // i.e. both namespace and project name were specified. Otherwise, GitLab
      // requires authentication and not all self-hosted platforms allow new
      // user registration.
      foreach ($repos as $name) {
        $query = $this->buildProjectQuery($namespace, $name, $filePath);
        $response = $this->getClient()->graphql()->execute($query->toString());
        if (!empty($response['data']['project'])) {
          yield $this->createHostingPlatformResult($namespace, $response['data']['project']);
        }
      }
    }
  }

  /**
   * Builds the group query.
   *
   * This renders a query similar to:
   * @code
   *
   * @endcode
   *
   * @param string $namespace
   *   The namespace.
   * @param string|null $filePath
   *   The file path, if one has been specified.
   * @param string|null $endCursor
   *   The end cursor, if any.
   *
   * @return \GraphQL\Actions\Query
   *   A GraphQL query builder instance.
   */
  protected function buildGroupQuery(string $namespace, ?string $filePath, ?string $endCursor = NULL): Query {
    $query = new Query('group', ['fullPath' => $namespace]);
    $query->alias('group');

    $filters = $endCursor ? ['after' => $endCursor] : [];

    $fragment = $query->projects($filters)
      ->pageInfo([])
      ->use('hasNextPage', 'endCursor')
      ->prev()
      ->nodes([])
      ->use('path', 'id', 'description')
      ->releases(['sort' => GitLabReleaseSort::CREATED_DESC, 'first' => 1])
      ->nodes([])
      ->use('createdAt')
      ->prev()
      ->prev()
      ->repository([])
      ->use('rootRef');

    if ($filePath) {
      $fragment->blobs(['paths' => [$filePath]])
        ->nodes([])
        ->use('rawBlob');
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
   *     releases(sort: CREATED_DESC first: 1) {
   *       nodes {
   *         createdAt
   *       }
   *     }
   *     repository {
   *       rootRef
   *       blobs(paths: ["README.md"]) {
   *         nodes {
   *           rawPath
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
   * @param string|null $filePath
   *   The file path, if one has been specified.
   *
   * @return \GraphQL\Actions\Query
   *   A GraphQL query builder instance.
   */
  protected function buildProjectQuery(string $namespace, string $name, ?string $filePath): Query {
    $query = new Query('project', ['fullPath' => "$namespace/$name"]);
    $fragment = $query->use('path', 'id', 'description')
      ->releases(['sort' => GitLabReleaseSort::CREATED_DESC, 'first' => 1])
      ->nodes([])
      ->use('createdAt')
      ->prev()
      ->prev()
      ->repository([])
      ->use('rootRef');

    if ($filePath) {
      $fragment->blobs(['paths' => [$filePath]])
        ->nodes([])
        ->use('rawBlob');
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
   * @return \Drupal\eu_oss_catalogue\Model\HostingPlatformResult
   *   A code hosting platform result instance.
   */
  protected function createHostingPlatformResult(string $namespace, array $project): HostingPlatformResult {
    return new HostingPlatformResult(
      $namespace,
      $project['path'],
      $project['repository']['rootRef'],
      $project['id'],
      $project['path'],
      $project['repository']['blobs']['nodes'][0]['rawBlob'] ?? NULL,
    );
  }

}
