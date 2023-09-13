<?php

declare(strict_types = 1);

namespace Drupal\eu_oss_catalogue\Plugin\eu_oss_catalogue\HostingPlatform;

use Drupal\Core\Form\FormStateInterface;
use Drupal\eu_oss_catalogue\Model\GitLabReleaseSort;
use Drupal\eu_oss_catalogue\Model\GitUri;
use Gitlab\Client;
use GraphQL\Actions\Query;

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
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'url' => NULL,
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $formState): array {
    $form = parent::buildConfigurationForm($form, $formState);

    // The list of namespaces or projects is not mandatory for self-hosted
    // GitLab code hosting platforms.
    $form['projects']['#required'] = FALSE;

    return [
      'url' => [
        '#type' => 'url',
        '#title' => $this->t('URL'),
        '#default_value' => $this->getConfiguration()['url'],
        '#required' => TRUE,
      ],
    ] + $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $formState): void {
    parent::submitConfigurationForm($form, $formState);
    $this->configuration['url'] = trim($formState->getValue('url'), ' /');
  }

  /**
   * {@inheritdoc}
   */
  public function isHostingPlatformForUrl(string $repositoryUrl): bool {
    if (
      !($gitUri = GitUri::fromUriString($repositoryUrl)) ||
      $gitUri->host === static::GITLAB_BASE_URL ||
      !$gitUri->isComplete()
    ) {
      return FALSE;
    }

    $namespaceParts = explode('/', $gitUri->namespace);
    $this->configuration['url'] = $host = "$gitUri->scheme://$gitUri->host";
    $client = $this->getClient();
    do {
      try {
        $fullPath = implode('/', $namespaceParts) . "/$gitUri->package";
        $query = new Query('project', ['fullPath' => $fullPath]);
        $query->repository([])->use('exists');
        $response = $client->graphql()->execute($query->toString());
      }
      catch (\Exception) {
        $host .= '/' . array_shift($namespaceParts);
        $client->setUrl($host);
        continue;
      }

      return !empty($response['data']['project']['repository']['exists']);
    } while (count($namespaceParts) > 0);

    return FALSE;
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
      $this->client->setUrl($this->getConfiguration()['url']);
    }
    return $this->client;
  }

  /**
   * {@inheritdoc}
   */
  protected function doGetProjects(?string $filePath = NULL): iterable {
    if (!empty($this->getConfiguration()['projects'])) {
      // A list of namespaces or projects has been specified.
      foreach (parent::doGetProjects($filePath) as $project) {
        yield $project;
      }
      return;
    }

    $endCursor = NULL;
    do {
      $query = $this->buildProjectsQuery($filePath, $endCursor);
      $response = $this->getClient()->graphql()->execute($query->toString());

      if (empty($response['data']['projects']['nodes'])) {
        // This platform has no public projects.
        break;
      }

      $hasNextPage = $response['data']['projects']['pageInfo']['hasNextPage'];
      $endCursor = $response['data']['projects']['pageInfo']['endCursor'];
      foreach ($response['data']['projects']['nodes'] as $project) {
        // Ignore if there's no default branch or the project is archived.
        if (!empty($project['repository']['rootRef']) && empty($project['archived'])) {
          // Extract the namespace from the full path.
          $parts = explode('/', $project['fullPath']);
          array_pop($parts);
          $namespace = implode('/', $parts);
          yield $this->createHostingPlatformResult($namespace, $project);
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
   *   projects(first: 100 after: "gtT54Weo") {
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
   * @param string|null $filePath
   *   The file path, if one has been specified.
   * @param string|null $endCursor
   *   The end cursor, if any.
   *
   * @return \GraphQL\Actions\Query
   *   A GraphQL query builder instance.
   */
  protected function buildProjectsQuery(?string $filePath, ?string $endCursor = NULL): Query {
    $filters = ['first' => 100] + ($endCursor ? ['after' => $endCursor] : []);

    $query = new Query('projects', $filters);
    $query->use('count');
    $query->pageInfo([])->use('hasNextPage', 'endCursor');

    $fragment = $query->nodes([])
      ->use('fullPath', 'path', 'id', 'description', 'archived')
      ->releases(['sort' => GitLabReleaseSort::CREATED_DESC, 'first' => 1])
      ->nodes([])
      ->use('tagName', 'createdAt')
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

}
