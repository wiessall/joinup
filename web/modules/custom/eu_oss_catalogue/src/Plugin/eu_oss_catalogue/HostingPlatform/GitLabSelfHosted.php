<?php

declare(strict_types=1);

namespace Drupal\eu_oss_catalogue\Plugin\eu_oss_catalogue\HostingPlatform;

use Drupal\Core\Form\FormStateInterface;
use Drupal\eu_oss_catalogue\Exception\HostingPlatformFetchException;
use Drupal\eu_oss_catalogue\Model\GitLab\ReleaseSort;
use Drupal\eu_oss_catalogue\Model\GitUri;
use Gitlab\Client;
use GraphQL\Actions\Query;
use Psr\Http\Message\ResponseInterface;

/**
 * Git code hosting platform for self-hosted GitLab repositories.
 *
 * @EuOssCatalogueHostingPlatform(
 *   id = "gitlab_self_hosted",
 *   label = @Translation("GitLab (self hosted)"),
 * )
 */
class GitLabSelfHosted extends GitLabDotCom {

  /**
   * Static cache for base URL detection.
   *
   * @var string[]
   */
  protected array $baseUrlCache = [];

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildConfigurationForm($form, $form_state);
    // The list of namespaces or projects is not mandatory for self-hosted
    // GitLab code hosting platforms.
    $form['projects']['#required'] = FALSE;
    $form['baseUrl']['#type'] = 'url';

    // Disable in case we are editing.
    if (!empty($this->getConfiguration()['baseUrl'])) {
      $form['baseUrl']['#disabled'] = TRUE;
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function isHostingPlatformForUrl(string $repositoryUrl): bool {
    return $this->getBaseFromRepoUrl($repositoryUrl) !== NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getBaseFromRepoUrl(string $repositoryUrl): ?string {
    if (!array_key_exists($repositoryUrl, $this->baseUrlCache)) {
      // Statically cache the findings as this is called more than once in the
      // same request.
      $this->baseUrlCache[$repositoryUrl] = $this->doGetBaseFromRepoUrl($repositoryUrl);
    }
    return $this->baseUrlCache[$repositoryUrl];
  }

  /**
   * Returns the self-hosted GitLab code hosting platform URL.
   *
   * @param string $repositoryUrl
   *   The project repository URL.
   *
   * @return string|null
   *   The determined base URL of the code hosting platform or NULL if it
   *   cannot be determined.
   */
  protected function doGetBaseFromRepoUrl(string $repositoryUrl): ?string {
    if (!$gitUri = GitUri::fromUriString($repositoryUrl)) {
      return NULL;
    }

    $namespaceParts = explode('/', $gitUri->namespace);
    $host = $gitUri->getSchemeAndHost();
    do {
      try {
        $response = $this->httpClient->get("$host/api");
      }
      catch (\Exception) {
        if ($namespaceParts >= 2) {
          $host .= '/' . array_shift($namespaceParts);
        }
        continue;
      }
      return $this->isGitLabApiResponse($response) ? $host : NULL;
    } while (count($namespaceParts) > 0);

    return NULL;
  }

  /**
   * Returns the GitLab client.
   *
   * @return \Gitlab\Client
   *   The GitLab client.
   */
  protected function getClient(): Client {
    if (!isset($this->client)) {
      $this->client = parent::getClient();
      $this->client->setUrl($this->getConfiguration()['baseUrl']);
    }
    return $this->client;
  }

  /**
   * {@inheritdoc}
   */
  protected function doGetProjects(array $requestedFiles = []): iterable {
    if (!empty($this->getConfiguration()['projects'])) {
      // A list of namespaces or projects has been specified.
      foreach (parent::doGetProjects($requestedFiles) as $project) {
        yield $project;
      }
      return;
    }

    $endCursor = NULL;
    do {
      $query = $this->buildProjectsQuery($requestedFiles, $endCursor);
      try {
        $response = $this->getClient()->graphql()->execute($query->toString());
      }
      catch (\RuntimeException $exception) {
        throw new HostingPlatformFetchException(message: $exception->getMessage(), previous: $exception);
      }

      if (empty($response['data']['projects']['nodes'])) {
        // This platform has no public projects.
        break;
      }

      $hasNextPage = $response['data']['projects']['pageInfo']['hasNextPage'];
      $endCursor = $response['data']['projects']['pageInfo']['endCursor'];
      foreach ($response['data']['projects']['nodes'] as $project) {
        // Ignore if there's no default branch (no default branch, no code).
        if (!empty($project['repository']['rootRef'])) {
          // Extract the namespace from the full path.
          $parts = explode('/', $project['fullPath']);
          array_pop($parts);
          $namespace = implode('/', $parts);
          yield $this->createProject($namespace, $project, $requestedFiles);
        }
      }
    } while ($hasNextPage);
  }

  /**
   * Builds a GraphQL GitLab projects query.
   *
   * This renders a query similar to:
   * @code
   * {
   *   projects(first: 100) {
   *     count
   *     pageInfo {
   *       hasNextPage
   *       endCursor
   *     }
   *     nodes {
   *       fullPath
   *       path
   *       id
   *       description
   *       archived
   *       forksCount
   *       starCount
   *       openIssuesCount
   *       avatarUrl
   *       openMergeRequestsCount
   *       releases(sort: CREATED_DESC first: 1) {
   *         nodes {
   *           tagName
   *           createdAt
   *         }
   *       }
   *       repository {
   *         rootRef
   *         blobs(paths: ["publiccode.yml"]) {
   *           nodes {
   *             rawBlob
   *           }
   *         }
   *       }
   *     }
   *   }
   * }
   * @endcode
   *
   * @param string[] $requestedFiles
   *   A list of file paths keyed by a file identifier.
   * @param string|null $endCursor
   *   The end cursor, if any.
   *
   * @return \GraphQL\Actions\Query
   *   A GraphQL query builder instance.
   */
  protected function buildProjectsQuery(array $requestedFiles = [], ?string $endCursor = NULL): Query {
    $filters = ['first' => 100] + ($endCursor ? ['after' => $endCursor] : []);

    $query = new Query('projects', $filters);
    $query->use('count');
    $query->pageInfo([])->use('hasNextPage', 'endCursor');

    $fragment = $query->nodes([])
      ->use(
        'fullPath',
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
   * Checks whether the HTTP response comes from a GitLab API endpoint.
   *
   * @param \Psr\Http\Message\ResponseInterface $response
   *   The HTTP response.
   *
   * @return bool
   *   Whether the response comes from a GitLab API endpoint.
   */
  protected function isGitLabApiResponse(ResponseInterface $response): bool {
    if (!$response->hasHeader('Set-Cookie')) {
      return FALSE;
    }
    foreach ($response->getHeader('Set-Cookie') as $item) {
      // GitLab API sets a session cookie.
      // @see https://docs.gitlab.com/ee/api/rest/#session-cookie
      if (str_starts_with($item, '_gitlab_session=')) {
        return TRUE;
      }
    }
    return FALSE;
  }

}
