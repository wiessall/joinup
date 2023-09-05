<?php

declare(strict_types = 1);

namespace Drupal\eu_oss_catalogue\Plugin\eu_oss_catalogue\HostingPlatform;

use Drupal\eu_oss_catalogue\EuOssCatalogueGitHostingPlatformPluginBase;
use Gitlab\Client;

/**
 * Base class for GitLab plugins.
 */
abstract class GitLabBase extends EuOssCatalogueGitHostingPlatformPluginBase {

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
      $this->client = new Client();
    }
    return $this->client;
  }

  /**
   * {@inheritdoc}
   */
  protected function getProjects(array $candidates, ?string $filePath = NULL): array {
    $projects = [];
    // @todo Implement.
    return $projects;
  }

}
