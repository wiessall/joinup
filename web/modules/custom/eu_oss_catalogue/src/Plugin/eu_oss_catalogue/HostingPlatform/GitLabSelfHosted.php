<?php

declare(strict_types = 1);

namespace Drupal\eu_oss_catalogue\Plugin\eu_oss_catalogue\HostingPlatform;

use Drupal\Core\Form\FormStateInterface;
use Drupal\eu_oss_catalogue\Model\HostingPlatformResult;
use Gitlab\Client;
use Gitlab\Exception\RuntimeException;
use Gitlab\ResultPager;

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
    // Self-hosted GitLab instances allows to crawl the whole platform.
    if (empty($candidates)) {
      $projects = [];
      $pager = new ResultPager($this->getClient());
      foreach ($pager->fetchAll($this->getClient()->projects(), 'all', ['archived' => FALSE]) as $repo) {
        $fileContent = NULL;
        if (!empty($repo['default_branch'])) {
          if ($filePath) {
            try {
              $fileContent = $this->getClient()->repositoryFiles()->getRawFile($repo['id'], $filePath, $repo['default_branch']);
            }
            catch (RuntimeException $exception) {
              if ($exception->getCode() !== 404) {
                throw $exception;
              }
              // Nothing to do, $fileContent is already NULL.
            }
          }

          $projects[$repo['namespace']['full_path']][$repo['path']] = new HostingPlatformResult(
            $repo['namespace']['full_path'],
            $repo['path'],
            $repo['default_branch'],
            (string) $repo['id'],
            $repo['description'],
            $fileContent,
          );
        }
      }
      return $projects;
    }
    return parent::getProjects($candidates, $filePath);
  }

}
