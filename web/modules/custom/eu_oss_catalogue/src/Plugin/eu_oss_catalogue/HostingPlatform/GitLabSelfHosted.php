<?php

declare(strict_types = 1);

namespace Drupal\eu_oss_catalogue\Plugin\eu_oss_catalogue\HostingPlatform;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Site\Settings;
use Drupal\eu_oss_catalogue\EuOssCatalogueGitHostingPlatformPluginBase;
use Gitlab\Client;

/**
 * Git code hosting platform for GitLab repositories.
 *
 * @EuOssCatalogueHostingPlatform(
 *   id = "gitlab_self_hosted",
 *   label = @Translation("GitLab (self hosted)"),
 * )
 */
class GitLabSelfHosted extends GitLabBase {

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
    $this->configuration['url'] = $formState->getValue('url');
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
  protected function getProjects(array $candidates, ?string $filePath = NULL): array {
    $projects = [];
    // @todo Implement.
    return $projects;
  }

}
