<?php

declare(strict_types = 1);

namespace Drupal\eu_oss_catalogue\Plugin\eu_oss_catalogue\HostingPlatform;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Site\Settings;
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
      $token = Settings::get('eu_oss_catalogue', [])['platform_gitlab']['access_token'][$this->getDerivativeId()] ?? NULL;
      if (!$token) {
        // @todo Should we show a message to user or it's only developer stuff?
        throw new \InvalidArgumentException('GitLab access token is not set');
      }
      $this->client->authenticate($token, Client::AUTH_HTTP_TOKEN);
    }
    return $this->client;
  }

  public function validateConfigurationForm(array &$form, FormStateInterface $formState): void {
    // @todo Temporarily, disable validation.
  }

}
