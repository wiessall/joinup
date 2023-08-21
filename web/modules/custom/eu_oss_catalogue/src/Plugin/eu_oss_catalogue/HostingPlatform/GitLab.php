<?php

declare(strict_types = 1);

namespace Drupal\eu_oss_catalogue_platform_gitlab\Plugin\eu_oss_catalogue\HostingPlatform;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Site\Settings;
use Drupal\eu_oss_catalogue\EuOssCatalogueGitHostingPlatformPluginBase;
use Gitlab\Client;

/**
 * Git code hosting platform for GitLab repositories.
 *
 * @EuOssCatalogueHostingPlatform(
 *   id = "gitlab",
 *   label = @Translation("GitLab"),
 * )
 */
class GitLab extends EuOssCatalogueGitHostingPlatformPluginBase {

  /**
   * Static cache for GitLab client.
   */
  protected Client $client;

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
    $state = [
      ':input[name="platformSettings[self_hosted]"]' => ['checked' => TRUE],
    ];
    return [
      'self_hosted' => [
        '#type' => 'checkbox',
        '#title' => $this->t('Self-hosted GitLab'),
        '#default_value' => !empty($this->getConfiguration()['url']),
      ],
      'url' => [
        '#type' => 'url',
        '#title' => $this->t('URL'),
        '#default_value' => $this->getConfiguration()['url'],
        '#states' => [
          'required' => $state,
          'visible' => $state,
        ],
      ],
    ] + parent::buildConfigurationForm($form, $formState);
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $formState): void {
    parent::validateConfigurationForm($form, $formState);
    if (empty($formState->getValue('self_hosted'))) {
      // Properly cast to NULL.
      $formState->setValue('url', NULL);
      $formState->setValue('projects', []);
    }
    $formState->unsetValue('self_hosted');
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
      $this->client = new Client();
      if ($url = $this->getConfiguration()['url']) {
        // Self-hosted GitLab.
        $this->client->setUrl($url);
      }
      $token = Settings::get('eu_oss_catalogue', [])['platform_gitlab']['access_token'][(string) $url] ?? NULL;
      if (!$token) {
        // @todo Should we show a message to user or it's only developer stuff?
        throw new \InvalidArgumentException('GitLab access token is not set');
      }
      $this->client->authenticate($token, Client::AUTH_HTTP_TOKEN);
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
